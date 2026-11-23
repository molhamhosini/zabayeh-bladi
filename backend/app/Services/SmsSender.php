<?php

namespace App\Services;

/** Delivers one-time login codes by SMS. */
interface SmsSender
{
    public function sendOtp(string $phone, string $code): void;
}
