<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('send_voucher_orders', function (Blueprint $table) {
            $table->enum('status', ['pending', 'processing', 'pending_otp', 'sent', 'failed', 'partially_failed', 'cancelled'])
                ->default('pending')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('send_voucher_orders', function (Blueprint $table) {
            $table->enum('status', ['pending', 'processing', 'sent', 'failed', 'partially_failed'])
                ->default('pending')
                ->change();
        });
    }
};
