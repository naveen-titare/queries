<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_balance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['credit', 'debit']);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2); // snapshot of balance after this transaction
            $table->string('note')->nullable();
            $table->foreignId('done_by')->constrained('users'); // which Avirqo staff did this
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_balance_logs');
    }
};
