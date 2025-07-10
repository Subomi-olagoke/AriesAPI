<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Comment extends Model {
    use HasFactory;
    
    protected $fillable = ['post_id', 'user_id', 'content', 'is_first', 'parent_id'];
    
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
    
    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }
    
    /**
     * Get the like count for this comment.
     *
     * @return int
     */
    public function getLikeCountAttribute()
    {
        // Check if the database has been migrated to support polymorphic relationships
        if (Schema::hasColumn('likes', 'likeable_type')) {
            return $this->likes()->count();
        } else {
            // If not yet migrated, use the old relationship structure
            return Like::where('comment_id', $this->id)->count();
        }
    }
}
