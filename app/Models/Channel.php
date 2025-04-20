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
        'creator_id',
        'share_link',
        'is_active',
        'max_members'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'max_members' => 'integer'
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
                $model->share_link = 'channel/' . Str::random(12);
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
     * Get all users in the channel.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'channel_members', 'channel_id', 'user_id')
            ->withPivot('role', 'is_active', 'joined_at', 'last_read_at')
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
            ->where('is_active', true)
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
        return $this->members()->where('is_active', true)->count() >= $this->max_members;
    }
}
