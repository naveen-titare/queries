<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * voucher_inventory: one row per (brand/product + denomination) held by Avirqo.
 * quantity_available = imported - shared.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_inventory', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('brand_name');
            $table->string('image_url')->nullable();
            $table->string('currency_code', 8)->default('INR');
            $table->decimal('denomination', 12, 2);

            $table->unsignedInteger('quantity_imported')->default(0);
            $table->unsignedInteger('quantity_shared')->default(0);
            $table->unsignedInteger('quantity_available')->default(0);

            $table->timestamps();

            $table->unique(['product_id', 'denomination', 'currency_code'], 'uniq_product_denom');
            $table->index('brand_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_inventory');
    }
};
