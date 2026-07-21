<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class VoucherCampaign extends Model {
    protected $fillable = ['name', 'description', 'is_active', 'required_otp_confirmation'];
    protected $casts = ['is_active' => 'boolean', 'required_otp_confirmation' => 'boolean'];
    public function products() { return $this->hasMany(VoucherCampaignProduct::class, 'campaign_id'); }
    public function customers() { return $this->belongsToMany(Customer::class, 'voucher_campaign_customers', 'campaign_id', 'customer_id')->withTimestamps(); }
}
