<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('billing_credit_note_applications');

        Schema::create('billing_credit_note_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('billing_credit_debit_note_id');
            $table->unsignedBigInteger('proforma_invoice_id');
            $table->unsignedBigInteger('customer_id');
            $table->decimal('amount', 14, 2);
            $table->string('status', 32)->default('reserved'); // reserved, applied, cancelled
            $table->unsignedBigInteger('reserved_by')->nullable();
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->foreign('billing_credit_debit_note_id', 'bcna_note_fk')->references('id')->on('billing_credit_debit_notes')->cascadeOnDelete();
            $table->foreign('proforma_invoice_id', 'bcna_pi_fk')->references('id')->on('proforma_invoices')->cascadeOnDelete();
            $table->foreign('customer_id', 'bcna_customer_fk')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('reserved_by', 'bcna_reserved_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->index(['customer_id', 'status'], 'bcna_customer_status_idx');
            $table->index(['proforma_invoice_id', 'status'], 'bcna_pi_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_credit_note_applications');
    }
};
