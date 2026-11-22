<?php

namespace App\Services;

use App\Models\OtpCode;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    public function __construct(private SmsSender $sms) {}

    public function send(string $phone): void
    {
        $length = config('shop.otp.length');
        $code = str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);

        OtpCode::where('phone', $phone)->delete();
        OtpCode::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(config('shop.otp.ttl_minutes')),
        ]);

        $this->sms->send($phone, "رمز التحقق في ذبائح بلادي: {$code}");
    }

    public function verify(string $phone, string $code): bool
    {
        $otp = OtpCode::where('phone', $phone)->latest('id')->first();
        if (! $otp || $otp->expires_at->isPast() || $otp->attempts >= config('shop.otp.max_attempts')) {
            return false;
        }
        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            return false;
        }
        $otp->delete();

        return true;
    }
}
