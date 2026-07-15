<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_products', function (Blueprint $table) {
            $table->id();
            $table->string('product_id')->unique(); // Xoxoday productId
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('image_url')->nullable();

            // Pricing & Denomination
            $table->string('currency_code', 10)->nullable();
            $table->string('currency_name')->nullable();
            $table->string('value_type', 20)->default('fixed'); // fixed or open
            $table->decimal('min_value', 12, 2)->nullable();
            $table->decimal('max_value', 12, 2)->nullable();
            $table->json('value_denominations')->nullable(); // array of available denominations

            // Geographic
            $table->string('country_name')->nullable();
            $table->string('country_code', 10)->nullable();
            $table->json('countries')->nullable();

            // Usage
            $table->string('usage_type', 20)->nullable(); // online, offline, both
            $table->string('delivery_type', 20)->nullable(); // realtime, delayed

            // Instructions
            $table->text('terms_and_conditions')->nullable();
            $table->text('redemption_instructions')->nullable();
            $table->text('expiry_and_validity')->nullable();

            // Fulfillment
            $table->unsignedInteger('order_quantity_limit')->nullable();
            $table->unsignedInteger('tat_in_days')->nullable();

            // Fees
            $table->decimal('fee', 8, 4)->default(0);
            $table->decimal('discount', 8, 4)->default(0);
            $table->decimal('exchange_rate', 10, 4)->default(1);
            $table->decimal('redemption_fee', 8, 4)->default(0);
            $table->string('redemption_fee_type', 20)->nullable();

            // Low stock threshold
            $table->unsignedInteger('low_stock_threshold')->default(10);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_products');
    }
};
