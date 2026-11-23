<?php

namespace App\Services;

use App\Models\Setting;

/** Resolves SMS settings: values saved in the admin panel win over .env defaults. */
class Sms
{
    public static function msg91Active(): bool
    {
        $saved = Setting::get('sms.msg91.active');

        return $saved !== null ? $saved === '1' : config('shop.sms.driver') === 'msg91';
    }

    /** @return array{base_url: string, auth_key: ?string, template_id: ?string, otp_variable: string} */
    public static function msg91(): array
    {
        $config = config('shop.sms.msg91');

        return [
            'base_url' => $config['base_url'],
            'auth_key' => Setting::get('sms.msg91.auth_key', $config['auth_key'], secret: true),
            'template_id' => Setting::get('sms.msg91.template_id', $config['template_id']),
            'otp_variable' => Setting::get('sms.msg91.otp_variable', $config['otp_variable']),
        ];
    }
}
