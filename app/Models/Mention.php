<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mention extends Model
{
    use HasFactory;

    protected $fillable = [
        'mentionable_id',
        'mentionable_type',
        'mentioned_user_id',
        'mentioned_by_user_id',
        'status' // 'read' or 'unread'
    ];

    /**
     * Get the parent mentionable model (message, post, or comment).
     */
    public function mentionable()
    {
        return $this->morphTo();
    }

    /**
     * Get the user who was mentioned.
     */
    public function mentionedUser()
    {
        return $this->belongsTo(User::class, 'mentioned_user_id');
    }

    /**
     * Get the user who created the mention.
     */
    public function mentionedByUser()
    {
        return $this->belongsTo(User::class, 'mentioned_by_user_id');
    }

    /**
     * Scope a query to only include unread mentions.
     */
    public function scopeUnread($query)
    {
        return $query->where('status', 'unread');
    }

    /**
     * Mark the mention as read.
     */
    public function markAsRead()
    {
        $this->status = 'read';
        return $this->save();
    }
}