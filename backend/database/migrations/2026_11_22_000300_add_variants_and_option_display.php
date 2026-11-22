<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Weight/age choices of one animal, each with its own price (e.g. 8-10 kg, 2-4 months).
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('weight_min_kg', 5, 1)->nullable();
            $table->decimal('weight_max_kg', 5, 1)->nullable();
            $table->string('age_ar')->nullable();
            $table->string('age_en')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->boolean('in_stock')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::table('option_groups', function (Blueprint $table) {
            $table->string('display', 12)->default('chips')->after('multiple'); // chips | cards (with images) | checklist
        });

        Schema::table('options', function (Blueprint $table) {
            $table->string('image')->nullable()->after('name_en');
            $table->boolean('default_selected')->default(false)->after('extra_price');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->string('variant_ar')->nullable()->after('name_en');
            $table->string('variant_en')->nullable()->after('variant_ar');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $t) => $t->dropConstrainedForeignId('product_variant_id'));
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn(['variant_ar', 'variant_en']));
        Schema::table('options', fn (Blueprint $t) => $t->dropColumn(['image', 'default_selected']));
        Schema::table('option_groups', fn (Blueprint $t) => $t->dropColumn('display'));
        Schema::dropIfExists('product_variants');
    }
};
