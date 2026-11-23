<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/** Key/value settings editable from the admin panel. Secret values are stored encrypted. */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null, bool $secret = false): mixed
    {
        $value = static::query()->where('key', $key)->value('value');
        if ($value === null || $value === '') {
            return $default;
        }

        return $secret ? Crypt::decryptString($value) : $value;
    }

    public static function put(string $key, ?string $value, bool $secret = false): void
    {
        static::query()->updateOrCreate(['key' => $key], [
            'value' => ($secret && filled($value)) ? Crypt::encryptString($value) : $value,
        ]);
    }
}
