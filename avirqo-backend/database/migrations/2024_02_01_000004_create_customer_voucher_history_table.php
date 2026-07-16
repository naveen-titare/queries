<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_voucher_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('voucher_name');
            $table->decimal('denomination', 10, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('total_deducted', 12, 2);
            $table->foreignId('sent_by')->constrained('users'); // Avirqo staff member
            $table->timestamp('sent_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_voucher_history');
    }
};
