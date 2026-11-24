<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Invoice header email (owner request 2026-11-24).
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(['key' => 'invoice.email'], ['value' => 'info@zabayeah.com', 'updated_at' => now(), 'created_at' => now()]);
    }

    public function down(): void {}
};
