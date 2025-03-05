<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'paystack_reference',
        'paystack_subscription_code',
        'paystack_email_token',
        'plan_type',
        'plan_code',
        'amount',
        'status',
        'starts_at',
        'expires_at',
        'is_active',
        'is_recurring'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'is_recurring' => 'boolean',
        'amount' => 'decimal:2',
    ];

    /**
     * Get the user that owns the subscription.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if subscription is valid.
     */
    public function isValid()
    {
        return $this->is_active && $this->expires_at->isFuture();
    }

    /**
     * Check if subscription is recurring.
     */
    public function isRecurring()
    {
        return $this->is_recurring && $this->status === 'active';
    }

    /**
     * Cancel the subscription.
     */
    public function cancel()
    {
        $this->is_active = false;
        $this->status = 'cancelled';
        return $this->save();
    }

    /**
     * Reactivate the subscription.
     */
    public function reactivate()
    {
        $this->is_active = true;
        $this->status = 'active';
        return $this->save();
    }

    /**
     * Get days remaining.
     */
    public function daysRemaining()
    {
        if (!$this->expires_at) {
            return 0;
        }
        
        return max(0, now()->diffInDays($this->expires_at));
    }
}