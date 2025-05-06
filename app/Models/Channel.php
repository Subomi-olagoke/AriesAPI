<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    use HasFactory;
    
    // Disable auto-incrementing ID
    public $incrementing = false;
    
    // Set ID type to string (for UUID)
    protected $keyType = 'string';
    
    protected $fillable = [
        'id',
        'title',
        'description',
        'picture',
        'creator_id',
        'share_link',
        'join_code',
        'is_active',
        'max_members',
        'requires_approval',
        'is_public'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'max_members' => 'integer',
        'requires_approval' => 'boolean',
        'is_public' => 'boolean'
    ];
    
    // Automatically generate UUID when creating a new channel
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Str::uuid()->toString();
            }
            
            if (empty($model->share_link)) {
                $model->share_link = 'https://ariesmvp-9903a26b3095.herokuapp.com/channel/' . $model->id;
            }
            
            if (empty($model->join_code)) {
                $model->join_code = Str::upper(Str::random(8));
            }
        });
    }
    
    /**
     * Get the creator of the channel.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
    
    /**
     * Get all members of the channel.
     */
    public function members()
    {
        return $this->hasMany(ChannelMember::class);
    }
    
    /**
     * Get all approved members of the channel.
     */
    public function approvedMembers()
    {
        return $this->members()
            ->where('status', 'approved')
            ->where('is_active', true);
    }
    
    /**
     * Get all pending members of the channel.
     */
    public function pendingMembers()
    {
        return $this->members()
            ->where('status', 'pending');
    }
    
    /**
     * Get all users in the channel.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'channel_members', 'channel_id', 'user_id')
            ->withPivot('role', 'status', 'is_active', 'joined_at', 'last_read_at', 'join_message')
            ->withTimestamps();
    }
    
    /**
     * Get all messages in the channel.
     */
    public function messages()
    {
        return $this->hasMany(ChannelMessage::class)->orderBy('created_at', 'asc');
    }
    
    /**
     * Get the latest message in the channel.
     */
    public function latestMessage()
    {
        return $this->hasOne(ChannelMessage::class)->latest();
    }
    
    /**
     * Check if a user is a member of the channel.
     */
    public function isMember(User $user)
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->where('is_active', true)
            ->exists();
    }
    
    /**
     * Check if a user is an admin of the channel.
     */
    public function isAdmin(User $user)
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->where('role', 'admin')
            ->where('status', 'approved')
            ->where('is_active', true)
            ->exists();
    }
    
    /**
     * Check if a user has a pending join request for the channel.
     */
    public function hasPendingRequest(User $user)
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();
    }
    
    /**
     * Get unread messages count for a user.
     */
    public function unreadMessagesCount(User $user)
    {
        $member = $this->members()->where('user_id', $user->id)->first();
        
        if (!$member || !$member->last_read_at) {
            return $this->messages->count();
        }
        
        return $this->messages()
            ->where('created_at', '>', $member->last_read_at)
            ->where('sender_id', '!=', $user->id)
            ->count();
    }
    
    /**
     * Check if channel has reached maximum members.
     */
    public function hasReachedMaxMembers()
    {
        return $this->approvedMembers()->count() >= $this->max_members;
    }
    
    /**
     * Check if the channel has any educator members.
     */
    public function hasEducators()
    {
        return $this->users()
            ->where('role', User::ROLE_EDUCATOR)
            ->where('channel_members.status', 'approved')
            ->where('channel_members.is_active', true)
            ->exists();
    }
    
    /**
     * Get all educator members in the channel.
     */
    public function educators()
    {
        return $this->users()
            ->where('role', User::ROLE_EDUCATOR)
            ->where('channel_members.status', 'approved')
            ->where('channel_members.is_active', true);
    }
    
    /**
     * Regenerate join code for the channel.
     */
    public function regenerateJoinCode()
    {
        $this->join_code = Str::upper(Str::random(8));
        $this->save();
        return $this->join_code;
    }
    
    /**
     * Get all collaborative spaces in the channel.
     */
    public function collaborativeSpaces()
    {
        return $this->hasMany(CollaborativeSpace::class);
    }
}