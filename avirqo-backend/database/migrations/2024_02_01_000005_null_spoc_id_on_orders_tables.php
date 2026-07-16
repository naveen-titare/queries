<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fix voucher_orders - change spoc_id FK to nullOnDelete
        Schema::table('voucher_orders', function (Blueprint $table) {
            $table->dropForeign(['spoc_id']);
            $table->foreignId('spoc_id')
                ->nullable()
                ->change()
                ->constrained('customer_spocs')
                ->nullOnDelete();
        });

        // Fix send_voucher_orders - change spoc_id FK to nullOnDelete
        Schema::table('send_voucher_orders', function (Blueprint $table) {
            $table->dropForeign(['spoc_id']);
            $table->foreignId('spoc_id')
                ->nullable()
                ->change()
                ->constrained('customer_spocs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Revert voucher_orders
        Schema::table('voucher_orders', function (Blueprint $table) {
            $table->dropForeign(['spoc_id']);
            $table->foreignId('spoc_id')
                ->nullable(false)
                ->change()
                ->constrained('customer_spocs');
        });

        // Revert send_voucher_orders
        Schema::table('send_voucher_orders', function (Blueprint $table) {
            $table->dropForeign(['spoc_id']);
            $table->foreignId('spoc_id')
                ->nullable(false)
                ->change()
                ->constrained('customer_spocs');
        });
    }
};