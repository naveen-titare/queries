<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('send_voucher_orders', function (Blueprint $table) {
            $table->foreignId('proforma_invoice_id')->nullable()->after('spoc_id')->constrained('proforma_invoices')->nullOnDelete();
            $table->foreignId('tax_invoice_id')->nullable()->after('proforma_invoice_id')->constrained('tax_invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('send_voucher_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_invoice_id');
            $table->dropConstrainedForeignId('proforma_invoice_id');
        });
    }
};
