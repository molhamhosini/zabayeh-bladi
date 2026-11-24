<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * MSG91 driver. Two MSG91 APIs are supported:
 *  - "otp"  (default): the SendOTP API, for an OTP template whose text contains ##OTP##.
 *  - "flow": the Flow API, for an SMS template with a custom variable (e.g. ##otp##).
 * Every call is logged (without the code) so failed deliveries can be traced from MSG91's request id.
 */
class Msg91SmsSender implements SmsSender
{
    public function sendOtp(string $phone, string $code): void
    {
        $config = Sms::msg91();
        if (blank($config['auth_key']) || blank($config['template_id'])) {
            throw new RuntimeException('MSG91 is not configured (Template ID and Auth Key in admin settings).');
        }
        $mobile = preg_replace('/\D/', '', $phone);
        $base = rtrim($config['base_url'], '/');
        $http = Http::withHeaders(['authkey' => $config['auth_key']])->acceptJson()->timeout(10);

        $response = $config['api'] === 'flow'
            ? $http->post($base.'/api/v5/flow', [
                'template_id' => $config['template_id'],
                'short_url' => '0',
                'recipients' => [['mobiles' => $mobile, $config['otp_variable'] => $code]],
            ])
            : $http->withQueryParameters([
                'template_id' => $config['template_id'],
                'mobile' => $mobile,
                'otp' => $code,
                'otp_length' => strlen($code),
            ])->post($base.'/api/v5/otp');

        $body = $response->json() ?? ['raw' => mb_substr($response->body(), 0, 300)];
        $masked = substr($mobile, 0, 5).'****'.substr($mobile, -3);
        Log::info("MSG91 {$config['api']} to {$masked}: HTTP {$response->status()} ".json_encode($body, JSON_UNESCAPED_UNICODE));

        if ($response->failed() || ($body['type'] ?? null) === 'error') {
            throw new RuntimeException('MSG91 send failed: '.($body['message'] ?? $response->status()));
        }
    }
}
