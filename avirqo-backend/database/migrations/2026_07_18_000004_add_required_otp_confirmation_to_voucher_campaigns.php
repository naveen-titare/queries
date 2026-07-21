<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voucher_campaigns', function (Blueprint $table) {
            $table->boolean('required_otp_confirmation')->default(false)->after('is_active');
        });

        DB::table('voucher_campaigns')->update(['required_otp_confirmation' => false]);
    }

    public function down(): void
    {
        Schema::table('voucher_campaigns', function (Blueprint $table) {
            $table->dropColumn('required_otp_confirmation');
        });
    }
};
