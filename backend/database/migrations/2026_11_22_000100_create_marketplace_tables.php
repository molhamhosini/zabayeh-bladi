<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emirates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emirate_id')->constrained()->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // A vendor is ذبائح بلادي itself (is_own = true) or a contracted butcher shop.
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('logo')->nullable();
            $table->boolean('is_own')->default(false);
            $table->decimal('commission_rate', 5, 2)->default(0); // % taken by the platform
            $table->string('trade_license_no')->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('emirate_id')->constrained();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('phone', 20)->nullable();
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->boolean('accepts_cod')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Which areas a branch delivers to, and at what fee.
        Schema::create('area_branch', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('min_order', 10, 2)->default(0);
            $table->unique(['branch_id', 'area_id']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('image')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained();
            $table->string('name_ar');
            $table->string('name_en');
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->decimal('weight_min_kg', 5, 1)->nullable();
            $table->decimal('weight_max_kg', 5, 1)->nullable();
            $table->decimal('price', 10, 2);
            $table->string('image')->nullable();
            $table->boolean('in_stock')->default(true);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // Cutting, packaging, head, offal ... defined by the platform, attached per product.
        Schema::create('option_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en');
            $table->boolean('required')->default(false);
            $table->boolean('multiple')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('option_group_id')->constrained()->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en');
            $table->decimal('extra_price', 10, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('option_group_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('option_group_id')->constrained()->cascadeOnDelete();
            $table->unique(['product_id', 'option_group_id']);
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->constrained();
            $table->string('label')->nullable();
            $table->string('details');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedSmallInteger('capacity')->default(10);
            $table->unsignedSmallInteger('booked')->default(0);
            $table->timestamps();
            $table->index(['branch_id', 'date']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('address_id')->constrained();
            $table->foreignId('delivery_slot_id')->nullable()->constrained();
            $table->string('purpose', 20)->default('normal'); // normal | udhiya | aqiqah | sadaqah
            $table->string('beneficiary_name')->nullable();
            $table->boolean('donate_to_charity')->default(false);
            $table->string('status', 30)->default('pending');
            $table->string('payment_method', 10)->default('cod'); // cod | card
            $table->string('payment_status', 15)->default('unpaid'); // unpaid | paid | refunded | failed
            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('vat', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->text('notes')->nullable();
            $table->foreignId('driver_id')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->string('name_ar');
            $table->string('name_en');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->json('options')->nullable();
            $table->decimal('line_total', 10, 2);
            $table->timestamps();
        });

        Schema::create('order_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30);
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->index();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['otp_codes', 'order_status_logs', 'order_items', 'orders', 'delivery_slots', 'addresses',
            'option_group_product', 'options', 'option_groups', 'products', 'categories', 'area_branch',
            'branches', 'vendors', 'areas', 'emirates'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
