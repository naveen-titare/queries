<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 32);
            $table->unsignedSmallInteger('financial_year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['document_type', 'financial_year']);
        });

        Schema::create('billing_otp_approvers', function (Blueprint $table) {
            $table->id();
            $table->string('group_key', 80)->unique();
            $table->string('label');
            $table->json('emails');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('proforma_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('draft_number')->unique();
            $table->string('pi_number')->nullable()->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('draft'); // draft, finalized, paid, partially_delivered, delivered, cancelled, reconciled
            $table->date('issue_date')->nullable();
            $table->date('valid_until')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('taxable_value', 14, 2)->default(0);
            $table->decimal('cgst_amount', 14, 2)->default(0);
            $table->decimal('sgst_amount', 14, 2)->default(0);
            $table->decimal('igst_amount', 14, 2)->default(0);
            $table->decimal('round_off', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('delivered_amount', 14, 2)->default(0);
            $table->decimal('balance_added_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'status']);
        });

        Schema::create('proforma_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proforma_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('send_voucher_products')->cascadeOnDelete();
            $table->string('brand')->nullable();
            $table->string('product_name');
            $table->string('hsn_sac')->nullable();
            $table->string('currency_code', 8)->default('INR');
            $table->decimal('denomination', 14, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 14, 2);
            $table->decimal('discount_percentage', 8, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('taxable_value', 14, 2)->default(0);
            $table->decimal('gst_rate', 8, 2)->default(0);
            $table->decimal('cgst_amount', 14, 2)->default(0);
            $table->decimal('sgst_amount', 14, 2)->default(0);
            $table->decimal('igst_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->decimal('delivered_amount', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('billing_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proforma_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('captured'); // captured, invalid
            $table->date('payment_date');
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_added_amount', 14, 2)->default(0);
            $table->decimal('credit_note_amount', 14, 2)->default(0);
            $table->string('mode', 50);
            $table->string('reference_no')->nullable();
            $table->text('details');
            $table->string('attachment_path')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->foreignId('invalidated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('invalid_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('billing_credit_debit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('draft_number')->unique();
            $table->string('note_number')->nullable()->unique();
            $table->string('type'); // credit, debit
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proforma_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tax_invoice_id')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained('billing_payments')->nullOnDelete();
            $table->string('status')->default('active'); // active, adjusted, cancelled
            $table->decimal('amount', 14, 2);
            $table->decimal('remaining_amount', 14, 2)->default(0);
            $table->string('reason')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'type', 'status']);
        });

        Schema::create('tax_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('draft_number')->unique();
            $table->string('invoice_number')->nullable()->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proforma_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('send_voucher_order_id')->nullable()->constrained('send_voucher_orders')->nullOnDelete();
            $table->string('status')->default('draft'); // draft, finalized, cancelled
            $table->date('invoice_date')->nullable();
            $table->string('place_of_supply')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('taxable_value', 14, 2)->default(0);
            $table->decimal('cgst_amount', 14, 2)->default(0);
            $table->decimal('sgst_amount', 14, 2)->default(0);
            $table->decimal('igst_amount', 14, 2)->default(0);
            $table->decimal('round_off', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'status']);
        });

        Schema::create('tax_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('send_voucher_products')->nullOnDelete();
            $table->string('brand')->nullable();
            $table->string('product_name');
            $table->string('hsn_sac')->nullable();
            $table->string('currency_code', 8)->default('INR');
            $table->decimal('denomination', 14, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 14, 2);
            $table->decimal('discount_percentage', 8, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('taxable_value', 14, 2)->default(0);
            $table->decimal('gst_rate', 8, 2)->default(0);
            $table->decimal('cgst_amount', 14, 2)->default(0);
            $table->decimal('sgst_amount', 14, 2)->default(0);
            $table->decimal('igst_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('billing_document_emails', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 50);
            $table->unsignedBigInteger('document_id');
            $table->string('to_email');
            $table->text('message')->nullable();
            $table->foreignId('sent_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['document_type', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_document_emails');
        Schema::dropIfExists('tax_invoice_items');
        Schema::dropIfExists('tax_invoices');
        Schema::dropIfExists('billing_credit_debit_notes');
        Schema::dropIfExists('billing_payments');
        Schema::dropIfExists('proforma_invoice_items');
        Schema::dropIfExists('proforma_invoices');
        Schema::dropIfExists('billing_otp_approvers');
        Schema::dropIfExists('billing_number_sequences');
    }
};
