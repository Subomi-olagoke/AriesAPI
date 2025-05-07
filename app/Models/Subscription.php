<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'subscription_plan_id',
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
        'is_recurring',
        'can_create_channels',
        'available_credits',
        'max_video_size_kb',
        'max_image_size_kb',
        'can_analyze_posts'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'is_recurring' => 'boolean',
        'can_create_channels' => 'boolean',
        'available_credits' => 'integer',
        'amount' => 'decimal:2',
        'max_video_size_kb' => 'integer',
        'max_image_size_kb' => 'integer',
        'can_analyze_posts' => 'boolean',
    ];

    /**
     * Get the user that owns the subscription.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Get the plan for this subscription.
     */
    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
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
    
    /**
     * Check if subscriber can create channels.
     */
    public function canCreateChannels()
    {
        return $this->isValid() && $this->can_create_channels;
    }
    
    /**
     * Add credits to the subscription.
     */
    public function addCredits($amount)
    {
        $this->available_credits += $amount;
        return $this->save();
    }
    
    /**
     * Use credits from the subscription.
     */
    public function useCredits($amount)
    {
        if ($this->available_credits < $amount) {
            return false;
        }
        
        $this->available_credits -= $amount;
        return $this->save();
    }
    
    /**
     * Check if subscription has access to premium content.
     */
    public function hasAccessToPremiumContent()
    {
        return $this->isValid();
    }
    
    /**
     * Check if subscription has access to live classes.
     */
    public function canJoinLiveClasses()
    {
        return $this->isValid();
    }
    
    /**
     * Get the maximum allowed video size in KB.
     */
    public function getMaxVideoSizeKb()
    {
        return $this->max_video_size_kb;
    }
    
    /**
     * Get the maximum allowed image size in KB.
     */
    public function getMaxImageSizeKb()
    {
        return $this->max_image_size_kb;
    }
    
    /**
     * Check if subscription can analyze posts with Cogni.
     */
    public function canAnalyzePosts()
    {
        return $this->isValid() && $this->can_analyze_posts;
    }
}