<?php

namespace App\Support;

use App\Models\Setting;

/** The seller details printed on tax invoices (set in admin > إعدادات الفاتورة). */
class InvoiceCompany
{
    public const FIELDS = ['name_ar', 'name_en', 'legal_name_ar', 'legal_name_en', 'trn', 'address_ar', 'address_en', 'phone', 'email', 'website', 'footer_note'];

    /** @return array<string, ?string> */
    public static function get(): array
    {
        $defaults = [
            'name_ar' => 'ذبائح بلادي',
            'name_en' => 'Zabayeh Bladi',
            'legal_name_ar' => 'نور الهدى لتجارة المواشي والاغنام ذ.م.م',
            'trn' => '105471107000001',
            'phone' => '0568888211 - 0568888210',
            'email' => 'info@zabayeah.com',
            'address_ar' => 'أبوظبي، الإمارات العربية المتحدة',
            'address_en' => 'Abu Dhabi, UAE',
            'footer_note' => 'شكراً لاختياركم ذبائح بلادي',
        ];
        $out = [];
        foreach (self::FIELDS as $f) {
            $out[$f] = Setting::get("invoice.$f", $defaults[$f] ?? null);
        }

        return $out;
    }

    public static function put(array $data): void
    {
        foreach (self::FIELDS as $f) {
            Setting::put("invoice.$f", isset($data[$f]) ? trim((string) $data[$f]) : null);
        }
    }
}
