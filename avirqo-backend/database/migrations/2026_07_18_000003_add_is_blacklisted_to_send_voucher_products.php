<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('send_voucher_products', function (Blueprint $table) {
            $table->boolean('is_blacklisted')->default(true)->after('is_active');
        });

        DB::table('send_voucher_products')->update(['is_blacklisted' => true]);
    }

    public function down(): void
    {
        Schema::table('send_voucher_products', function (Blueprint $table) {
            $table->dropColumn('is_blacklisted');
        });
    }
};
