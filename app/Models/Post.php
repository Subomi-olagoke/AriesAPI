<?php
// Modified version of app/Models/Post.php

namespace App\Models;

use App\Notifications\MentionNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Post extends Model {
    use HasFactory;

    protected $fillable = [
        'title', 
        'body', 
        'user_id', 
        'media_link', 
        'media_type', 
        'media_thumbnail', 
        'visibility',
        'original_filename',
        'mime_type',
        'share_key',
    ];

    protected $appends = ['file_extension', 'share_url'];

    // Allow visibility to be added to JSON serialization
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Define the visibility attribute
    protected $attributes = [
        'visibility' => 'public', // Default to public
    ];

    protected static function boot()
    {
        parent::boot();

        // Generate share key only ONCE when creating
        static::creating(function ($post) {
            // Generate a stable, unique share key if not already set
            if (!$post->share_key) {
                $post->share_key = hash('sha256', 
                    ($post->user_id ?? 'unknown') . 
                    now()->timestamp . 
                    Str::random(16)
                );
            }
        });

        // Prevent modifications to share_key
        static::updating(function ($post) {
            // If someone tries to change the share_key, revert it
            if ($post->isDirty('share_key')) {
                $post->share_key = $post->getOriginal('share_key');
            }
        });
    }

    public function user() {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function comments() {
        return $this->hasMany(Comment::class, 'post_id');
    }

    /**
     * Get the readlist items for this post.
     */
    public function readlistItems()
    {
        return $this->morphMany(ReadlistItem::class, 'item');
    }
    
    /**
     * Get the likes for this post.
     */
    public function likes()
    {
        return $this->hasMany(Like::class, 'post_id');
    }

    /**
     * Get the file extension attribute based on original filename
     */
    public function getFileExtensionAttribute()
    {
        if (!$this->original_filename) {
            return null;
        }

        $parts = explode('.', $this->original_filename);
        return end($parts);
    }
    
    /**
     * Get mentions in this post.
     */
    public function mentions()
    {
        return $this->morphMany(Mention::class, 'mentionable');
    }

    /**
     * Check if the post can be shared
     */
    public function canBeShared()
    {
        return $this->visibility === 'public';
    }

    /**
     * Get the shareable URL for this post
     */
    public function getShareUrlAttribute()
    {
        if ($this->share_key && $this->canBeShared()) {
            return route('posts.shared', ['shareKey' => $this->share_key]);
        }
        return null;
    }

    /**
     * Process and create mentions from post text.
     *
     * @param string $text The post text
     * @return void
     */
    public function processMentions($text)
    {
        // Extract mentions from text (@username format)
        preg_match_all('/@([a-zA-Z0-9_]+)/', $text, $matches);
        
        if (!empty($matches[1])) {
            $usernames = array_unique($matches[1]);
            
            foreach ($usernames as $username) {
                // Find the mentioned user
                $mentionedUser = User::where('username', $username)->first();
                
                if ($mentionedUser && $mentionedUser->id !== $this->user_id) {
                    // Create mention record
                    $mention = $this->mentions()->create([
                        'mentioned_user_id' => $mentionedUser->id,
                        'mentioned_by_user_id' => $this->user_id,
                        'status' => 'unread'
                    ]);
                    
                    // Send notification to the mentioned user
                    $mentionedUser->notify(new MentionNotification($mention));
                }
            }
        }
    }

    /**
     * Scope a query to only include public posts
     */
    public function scopePublic($query)
    {
        return $query->where('visibility', 'public');
    }

    /**
     * Scope a query to only include posts by followed users
     */
    public function scopeFollowers($query, User $user)
    {
        return $query->where('visibility', 'followers')
            ->whereHas('user.followers', function($q) use ($user) {
                $q->where('user_id', $user->id);
            });
    }
}