<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('order_otp_code', 6)->nullable()->after('balance');
            $table->timestamp('order_otp_expires_at')->nullable()->after('order_otp_code');
            $table->timestamp('order_otp_verified_at')->nullable()->after('order_otp_expires_at');
            $table->unsignedBigInteger('order_otp_verified_by')->nullable()->after('order_otp_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'order_otp_code', 'order_otp_expires_at', 'order_otp_verified_at', 'order_otp_verified_by'
            ]);
        });
    }
};