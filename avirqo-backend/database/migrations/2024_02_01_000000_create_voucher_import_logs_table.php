<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * voucher_import_logs: audit trail of every fetch/import attempt from Xoxoday,
 * with success or error message and a snapshot of what was selected.
 * (Created first so voucher_codes can FK to it.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_import_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();

            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('brand_name')->nullable();
            $table->decimal('denomination', 12, 2)->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->decimal('total_value', 14, 2)->nullable();
            $table->string('currency_code', 8)->default('INR');

            $table->enum('status', ['success', 'error']);
            $table->string('message', 1000)->nullable();

            $table->unsignedBigInteger('xoxoday_order_id')->nullable();
            $table->string('po_number')->nullable();

            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_import_logs');
    }
};
