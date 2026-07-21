<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->string('discount_type', 40)->default('campaign')->after('valid_until');
            $table->decimal('invoice_discount_percentage', 8, 2)->default(0)->after('discount_type');
        });
    }

    public function down(): void
    {
        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'invoice_discount_percentage']);
        });
    }
};
