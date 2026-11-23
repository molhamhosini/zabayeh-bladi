<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * MSG91 driver (SMS_DRIVER=msg91). Sends the code through an approved MSG91 SMS template ("Flow"),
 * e.g. "رمز التحقق في ذبائح بلادي: ##otp##". The template variable name is configurable.
 */
class Msg91SmsSender implements SmsSender
{
    public function sendOtp(string $phone, string $code): void
    {
        $config = Sms::msg91();
        if (blank($config['auth_key']) || blank($config['template_id'])) {
            throw new RuntimeException('MSG91 is not configured (Template ID and Auth Key in admin settings).');
        }

        $response = Http::withHeaders(['authkey' => $config['auth_key']])
            ->acceptJson()
            ->timeout(10)
            ->post(rtrim($config['base_url'], '/').'/api/v5/flow', [
                'template_id' => $config['template_id'],
                'short_url' => '0',
                'recipients' => [[
                    'mobiles' => ltrim($phone, '+'),
                    $config['otp_variable'] => $code,
                ]],
            ]);

        if ($response->failed() || $response->json('type') === 'error') {
            throw new RuntimeException('MSG91 send failed: '.$response->body());
        }
    }
}
