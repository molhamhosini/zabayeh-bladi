<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('compare_at_price', 10, 2)->nullable()->after('price'); // old price, shows a discount badge
            $table->boolean('is_featured')->default(false)->after('active');      // appears in the home "offers" row
        });

        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title_ar')->nullable();
            $table->string('title_en')->nullable();
            $table->string('subtitle_ar')->nullable();
            $table->string('subtitle_en')->nullable();
            $table->string('image')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete(); // tap opens this section
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['compare_at_price', 'is_featured']));
    }
};
