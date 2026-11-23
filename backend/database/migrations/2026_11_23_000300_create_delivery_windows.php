<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Delivery hours per emirate (e.g. Dubai 9-12/12-3/3-6, Abu Dhabi 10-1/4-7). Slots are generated from these.
        Schema::create('delivery_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emirate_id')->constrained()->cascadeOnDelete();
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedSmallInteger('capacity')->default(10);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['emirate_id', 'active']);
        });

        Schema::table('delivery_slots', function (Blueprint $table) {
            $table->foreignId('emirate_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->index(['branch_id', 'emirate_id', 'date']);
        });

        // Start every emirate on the standard windows 9-12, 12-3, 3-6.
        $now = now();
        foreach (DB::table('emirates')->pluck('id') as $emirateId) {
            foreach ([['09:00:00', '12:00:00'], ['12:00:00', '15:00:00'], ['15:00:00', '18:00:00']] as [$s, $e]) {
                DB::table('delivery_windows')->insert(['emirate_id' => $emirateId, 'starts_at' => $s, 'ends_at' => $e,
                    'capacity' => 10, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        // Existing slots were per branch only; tie them to the branch's own emirate.
        DB::table('delivery_slots')->whereNull('emirate_id')->update([
            'emirate_id' => DB::raw('(select emirate_id from branches where branches.id = delivery_slots.branch_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('delivery_slots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('emirate_id');
        });
        Schema::dropIfExists('delivery_windows');
    }
};
