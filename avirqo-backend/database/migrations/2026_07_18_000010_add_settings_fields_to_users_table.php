<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('employee_id')->nullable()->after('phone');
            $table->enum('status', ['active', 'inactive', 'temporary_disabled'])->default('active')->after('employee_id');
            $table->boolean('is_admin')->default(false)->after('status');
            $table->string('profile_photo_path')->nullable()->after('is_admin');
            $table->json('module_access')->nullable()->after('profile_photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'employee_id',
                'status',
                'is_admin',
                'profile_photo_path',
                'module_access',
            ]);
        });
    }
};
