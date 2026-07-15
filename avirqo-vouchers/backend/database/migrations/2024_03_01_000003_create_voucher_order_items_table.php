<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('voucher_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('voucher_products');
            $table->decimal('denomination', 12, 2);
            $table->string('currency_code', 10)->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('total_value', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_order_items');
    }
};
