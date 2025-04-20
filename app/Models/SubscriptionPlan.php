<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'name',
        'code',
        'type',
        'price',
        'description',
        'features',
        'paystack_plan_code',
        'is_active'
    ];
    
    protected $casts = [
        'price' => 'decimal:2',
        'features' => 'array',
        'is_active' => 'boolean'
    ];
    
    /**
     * Get all subscriptions for this plan.
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
    
    /**
     * Get the monthly price in formatted currency.
     */
    public function getFormattedPriceAttribute()
    {
        return '$' . number_format($this->price, 2);
    }
    
    /**
     * Get active plans.
     */
    public static function getActivePlans()
    {
        return self::where('is_active', true)->get();
    }
    
    /**
     * Check if this is a monthly plan.
     */
    public function isMonthly()
    {
        return $this->type === 'monthly';
    }
    
    /**
     * Check if this is a yearly plan.
     */
    public function isYearly()
    {
        return $this->type === 'yearly';
    }
    
    /**
     * Get the duration in days.
     */
    public function getDurationInDays()
    {
        return $this->isMonthly() ? 30 : 365;
    }
}
