<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Invoice header address (owner request 2026-11-24).
return new class extends Migration
{
    public function up(): void
    {
        foreach (['invoice.address_ar' => 'أبوظبي، الإمارات العربية المتحدة', 'invoice.address_en' => 'Abu Dhabi, UAE'] as $key => $value) {
            DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now(), 'created_at' => now()]);
        }
    }

    public function down(): void {}
};
