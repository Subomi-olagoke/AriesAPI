<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model {
    use HasFactory;
    
    protected $fillable = ['post_id', 'user_id', 'content', 'is_first'];
    
    protected $with = ['user']; // Always load the user relationship
    
    protected $appends = ['like_count'];
    

    public function likes() {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function post() {
        return $this->belongsTo(Post::class, 'post_id');
    }
    
    /**
     * Get the like count for this comment.
     *
     * @return int
     */
    public function getLikeCountAttribute()
    {
        return $this->likes()->count();
    }
}
