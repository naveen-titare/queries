<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('voucher_products')->cascadeOnDelete();
            $table->decimal('denomination', 12, 2);
            $table->string('currency_code', 10)->nullable();

            // Encrypted at rest using Laravel's encrypted cast + APP_KEY
            // Never stored plain, never logged
            $table->text('code_encrypted');       // the voucher code
            $table->text('pin_encrypted')->nullable(); // optional PIN

            $table->date('expiry_date')->nullable();

            $table->enum('status', ['available', 'reserved', 'sent'])->default('available');
            $table->foreignId('order_item_id')->nullable()->constrained('voucher_order_items')->nullOnDelete();

            $table->timestamps();

            $table->index(['product_id', 'denomination', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_codes');
    }
};
