<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class VoucherCampaignProduct extends Model {
    protected $fillable = ['campaign_id', 'product_id', 'discount_percentage', 'is_blacklisted'];
    protected $casts = ['discount_percentage' => 'decimal:2', 'is_blacklisted' => 'boolean'];
    public function product() { return $this->belongsTo(SendVoucherProduct::class, 'product_id'); }
    public function campaign() { return $this->belongsTo(VoucherCampaign::class, 'campaign_id'); }
}
