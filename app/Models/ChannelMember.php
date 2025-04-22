<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelMember extends Model
{
    use HasFactory;
    
    // Disable auto-incrementing ID
    public $incrementing = false;
    
    // Set ID type to string (for UUID)
    protected $keyType = 'string';
    
    protected $fillable = [
        'id',
        'channel_id',
        'user_id',
        'role',
        'status',
        'join_message',
        'rejection_reason',
        'is_active',
        'joined_at',
        'last_read_at'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'joined_at' => 'datetime',
        'last_read_at' => 'datetime'
    ];
    
    // Automatically generate UUID when creating a new channel member
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Str::uuid()->toString();
            }
        });
    }
    
    /**
     * Get the channel this member belongs to.
     */
    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }
    
    /**
     * Get the user this member represents.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Update the last read timestamp.
     */
    public function markAsRead()
    {
        $this->update(['last_read_at' => now()]);
    }
    
    /**
     * Check if this member is an admin.
     */
    public function isAdmin()
    {
        return $this->role === 'admin';
    }
    
    /**
     * Check if this member is pending approval.
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }
    
    /**
     * Check if this member is approved.
     */
    public function isApproved()
    {
        return $this->status === 'approved';
    }
    
    /**
     * Check if this member is rejected.
     */
    public function isRejected()
    {
        return $this->status === 'rejected';
    }
    
    /**
     * Approve this member.
     */
    public function approve()
    {
        $this->status = 'approved';
        $this->joined_at = now();
        $this->save();
        
        return $this;
    }
    
    /**
     * Reject this member with an optional reason.
     */
    public function reject($reason = null)
    {
        $this->status = 'rejected';
        $this->rejection_reason = $reason;
        $this->save();
        
        return $this;
    }
    
    /**
     * Get unread messages count for this member.
     */
    public function unreadMessagesCount()
    {
        if (!$this->last_read_at) {
            return $this->channel->messages->count();
        }
        
        return $this->channel->messages()
            ->where('created_at', '>', $this->last_read_at)
            ->where('sender_id', '!=', $this->user_id)
            ->count();
    }
}