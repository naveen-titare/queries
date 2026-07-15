<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('send_voucher_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('send_voucher_products')->cascadeOnDelete();
            $table->decimal('denomination', 12, 2);
            $table->string('currency_code', 10)->nullable();

            // Encrypted at rest using Laravel's encrypted cast + APP_KEY
            $table->text('code_encrypted');
            $table->text('pin_encrypted')->nullable();

            $table->date('expiry_date')->nullable();

            $table->enum('status', ['available', 'reserved', 'sent', 'failed'])->default('available');
            $table->foreignId('order_item_id')->nullable()->constrained('send_voucher_order_items')->nullOnDelete();

            $table->timestamps();

            $table->index(['product_id', 'denomination', 'status'], 'sv_codes_prod_denom_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('send_voucher_codes');
    }
};
