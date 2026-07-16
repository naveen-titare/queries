<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Holds a newly generated secret until the user proves they
            // scanned it correctly. Only promoted to google2fa_secret on
            // successful confirmation.
            $table->text('pending_google2fa_secret')->nullable()->after('google2fa_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pending_google2fa_secret');
        });
    }
};
