<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_spocs', function (Blueprint $table) {
            // Status: active, inactive, pending_verification
            $table->enum('status', ['active', 'inactive', 'pending_verification'])
                ->default('active')
                ->after('is_primary');
            
            // OTP fields for verification
            $table->string('otp_code', 6)->nullable()->after('status');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_code');
            $table->unsignedBigInteger('otp_verified_by')->nullable()->after('otp_expires_at');
            $table->timestamp('otp_verified_at')->nullable()->after('otp_verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('customer_spocs', function (Blueprint $table) {
            $table->dropColumn([
                'status', 'otp_code', 'otp_expires_at', 'otp_verified_by', 'otp_verified_at'
            ]);
        });
    }
};