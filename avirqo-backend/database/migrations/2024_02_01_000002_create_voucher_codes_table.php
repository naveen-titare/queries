<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * voucher_codes: individual voucher codes returned by Xoxoday on import.
 * Codes/pins are encrypted at rest (see App\Models\VoucherCode).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained('voucher_inventory')->cascadeOnDelete();
            $table->foreignId('import_log_id')->nullable()->constrained('voucher_import_logs')->nullOnDelete();

            $table->unsignedBigInteger('xoxoday_order_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->decimal('denomination', 12, 2);
            $table->string('currency_code', 8)->default('INR');

            $table->text('voucher_code');           // encrypted
            $table->text('pin')->nullable();        // encrypted
            $table->date('validity')->nullable();

            $table->enum('status', ['in_stock', 'shared', 'expired'])->default('in_stock');
            $table->unsignedBigInteger('shared_customer_id')->nullable();
            $table->timestamp('shared_at')->nullable();

            $table->timestamps();

            $table->index(['product_id', 'denomination']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_codes');
    }
};
