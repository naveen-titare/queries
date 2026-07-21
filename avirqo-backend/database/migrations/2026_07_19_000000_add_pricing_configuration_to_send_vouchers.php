<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('send_voucher_products', function(Blueprint $table){ $table->decimal('global_margin_percentage', 7, 2)->default(0)->after('discount'); });
  Schema::table('send_voucher_order_items', function(Blueprint $table){ $table->decimal('global_margin_percentage',7,2)->default(0)->after('gross_total'); $table->decimal('global_margin_amount',12,2)->default(0)->after('global_margin_percentage'); });
  Schema::table('send_voucher_orders', function(Blueprint $table){ $table->string('pricing_mode',20)->default('product')->after('total_amount'); $table->decimal('invoice_discount_percentage',7,2)->default(0)->after('pricing_mode'); $table->decimal('invoice_discount_amount',12,2)->default(0)->after('invoice_discount_percentage'); $table->decimal('products_subtotal',12,2)->default(0)->after('invoice_discount_amount'); });
 }
 public function down(): void {
  Schema::table('send_voucher_orders', function(Blueprint $table){$table->dropColumn(['pricing_mode','invoice_discount_percentage','invoice_discount_amount','products_subtotal']);});
  Schema::table('send_voucher_order_items', function(Blueprint $table){$table->dropColumn(['global_margin_percentage','global_margin_amount']);});
  Schema::table('send_voucher_products', function(Blueprint $table){$table->dropColumn('global_margin_percentage');});
 }
};
