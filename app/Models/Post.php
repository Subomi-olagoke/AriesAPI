<?php

namespace App\Models;

use App\Models\User;

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
}