<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('send_voucher_products')->cascadeOnDelete();
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->boolean('is_blacklisted')->default(false);
            $table->timestamps();

            $table->unique(['customer_id', 'product_id']);
            $table->index(['customer_id', 'is_blacklisted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_products');
    }
};
