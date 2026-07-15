<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('send_voucher_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique(); // AVQ-SEND-2026-00001 - generated from ID
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spoc_id')->constrained('customer_spocs');
            $table->foreignId('sent_by')->constrained('users');
            $table->decimal('total_amount', 12, 2);
            $table->decimal('customer_balance_before', 12, 2);
            $table->decimal('customer_balance_after', 12, 2);
            $table->enum('status', ['pending', 'processing', 'sent', 'failed', 'partially_failed'])->default('pending');
            $table->string('email_sent_to')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->unsignedInteger('email_attempts')->default(0);
            $table->unsignedBigInteger('total_codes_count')->default(0);
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index('order_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('send_voucher_orders');
    }
};
