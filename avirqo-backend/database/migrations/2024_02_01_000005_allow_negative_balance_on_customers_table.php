<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Remove unsigned constraint so balance can go negative
            $table->decimal('balance', 12, 2)->default(0.00)->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('balance', 12, 2)->unsigned()->default(0.00)->change();
        });
    }
};
