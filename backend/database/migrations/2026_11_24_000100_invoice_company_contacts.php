<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Invoice header: no website line, company phone numbers instead (owner request 2026-11-24).
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('key', 'invoice.website')->delete();
        DB::table('settings')->updateOrInsert(['key' => 'invoice.phone'], ['value' => '0568888211 - 0568888210', 'updated_at' => now(), 'created_at' => now()]);
    }

    public function down(): void {}
};
