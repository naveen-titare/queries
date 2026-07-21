<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('send_voucher_orders', function (Blueprint $table) {
            $table->string('spoc_name')->nullable()->after('spoc_id');
            $table->string('spoc_email')->nullable()->after('spoc_name');
        });
    }

    public function down(): void
    {
        Schema::table('send_voucher_orders', function (Blueprint $table) {
            $table->dropColumn(['spoc_name', 'spoc_email']);
        });
    }
};
