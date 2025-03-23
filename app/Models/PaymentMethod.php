<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'paystack_authorization_code',
        'paystack_customer_code',
        'card_type',
        'last_four',
        'expiry_month',
        'expiry_year',
        'bank_name',
        'is_default',
        'is_active'
    ];
    
    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];
    
    protected $hidden = [
        'paystack_authorization_code',
        'paystack_customer_code'
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}