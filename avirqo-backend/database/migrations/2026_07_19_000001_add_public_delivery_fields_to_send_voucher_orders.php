<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('send_voucher_orders', function (Blueprint $table) {
            $table->string('delivery_secret_hash')->nullable()->after('codes_hash');
            $table->timestamp('delivery_secret_expires_at')->nullable()->after('delivery_secret_hash');
            $table->timestamp('delivery_secret_used_at')->nullable()->after('delivery_secret_expires_at');
            $table->timestamp('delivery_secret_sent_at')->nullable()->after('delivery_secret_used_at');
            $table->string('delivery_secret_sent_to')->nullable()->after('delivery_secret_sent_at');

            $table->string('delivery_otp_hash')->nullable()->after('delivery_secret_sent_to');
            $table->timestamp('delivery_otp_expires_at')->nullable()->after('delivery_otp_hash');
            $table->timestamp('delivery_otp_sent_at')->nullable()->after('delivery_otp_expires_at');
            $table->string('delivery_otp_sent_to')->nullable()->after('delivery_otp_sent_at');
            $table->timestamp('delivery_otp_verified_at')->nullable()->after('delivery_otp_sent_to');

            $table->timestamp('delivery_downloaded_at')->nullable()->after('delivery_otp_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('send_voucher_orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_secret_hash',
                'delivery_secret_expires_at',
                'delivery_secret_used_at',
                'delivery_secret_sent_at',
                'delivery_secret_sent_to',
                'delivery_otp_hash',
                'delivery_otp_expires_at',
                'delivery_otp_sent_at',
                'delivery_otp_sent_to',
                'delivery_otp_verified_at',
                'delivery_downloaded_at',
            ]);
        });
    }
};
