<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('send_voucher_orders', function (Blueprint $table) {
            // SHA256 hash of all voucher codes sent in this order for verification
            // Format: hash('sha256', implode(',', sorted_code_ids))
            $table->string('codes_hash', 64)->nullable()->after('total_codes_count');
            $table->index('codes_hash');
        });
    }

    public function down(): void
    {
        Schema::table('send_voucher_orders', function (Blueprint $table) {
            $table->dropIndex(['codes_hash']);
            $table->dropColumn('codes_hash');
        });
    }
};