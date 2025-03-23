<?php
// app/Models/Post.php

namespace App\Models;

use App\Models\User;
use App\Notifications\MentionNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
        'mime_type'
    ];

    protected $appends = ['file_extension'];

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
}