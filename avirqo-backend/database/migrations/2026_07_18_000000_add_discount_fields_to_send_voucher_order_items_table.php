<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('send_voucher_order_items', function (Blueprint $table) {
            $table->decimal('gross_total', 12, 2)->nullable()->after('quantity');
            $table->decimal('discount_percentage', 5, 2)->default(0)->after('gross_total');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('discount_percentage');
        });
    }
    public function down(): void
    {
        Schema::table('send_voucher_order_items', function (Blueprint $table) {
            $table->dropColumn(['gross_total', 'discount_percentage', 'discount_amount']);
        });
    }
};
