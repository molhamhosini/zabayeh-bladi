<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/** Development SMS driver: writes the message to the log. Swap for a real provider (e.g. Twilio, Unifonic) in production. */
class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        Log::info("SMS to {$phone}: {$message}");
    }
}
