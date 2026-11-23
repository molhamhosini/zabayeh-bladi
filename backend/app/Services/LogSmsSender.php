<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/** Development driver (SMS_DRIVER=log): writes the code to the log instead of sending it. */
class LogSmsSender implements SmsSender
{
    public function sendOtp(string $phone, string $code): void
    {
        Log::info("SMS to {$phone}: رمز التحقق في ذبائح بلادي: {$code}");
    }
}
