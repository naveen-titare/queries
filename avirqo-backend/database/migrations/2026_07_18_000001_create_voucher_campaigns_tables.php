<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('voucher_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('voucher_campaign_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('voucher_campaigns')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('send_voucher_products')->cascadeOnDelete();
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->boolean('is_blacklisted')->default(false);
            $table->timestamps();
            $table->unique(['campaign_id', 'product_id']);
        });
        Schema::create('voucher_campaign_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('voucher_campaigns')->cascadeOnDelete();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['campaign_id', 'customer_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('voucher_campaign_customers');
        Schema::dropIfExists('voucher_campaign_products');
        Schema::dropIfExists('voucher_campaigns');
    }
};
