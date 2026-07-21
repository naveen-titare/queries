<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE send_voucher_codes MODIFY status ENUM('available', 'reserved', 'sent', 'used', 'failed') NOT NULL DEFAULT 'available'");
    }

    public function down(): void
    {
        DB::statement("UPDATE send_voucher_codes SET status = 'sent' WHERE status = 'used'");
        DB::statement("ALTER TABLE send_voucher_codes MODIFY status ENUM('available', 'reserved', 'sent', 'failed') NOT NULL DEFAULT 'available'");
    }
};
