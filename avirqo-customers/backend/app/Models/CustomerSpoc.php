<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerSpoc extends Model
{
    protected $fillable = ['customer_id', 'name', 'email', 'phone', 'is_primary', 'user_id'];

    protected $casts = ['is_primary' => 'boolean'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
