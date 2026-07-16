<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Stored encrypted at the model level (see User::casts)
            $table->text('google2fa_secret')->nullable()->after('password');
            $table->boolean('two_factor_enabled')->default(false)->after('google2fa_secret');
            $table->unsignedTinyInteger('failed_2fa_attempts')->default(0)->after('two_factor_enabled');
            $table->timestamp('locked_until')->nullable()->after('failed_2fa_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google2fa_secret', 'two_factor_enabled', 'failed_2fa_attempts', 'locked_until']);
        });
    }
};
