<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Like extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'likeable_id', 'likeable_type'];

    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the parent likeable model (post, comment, or course).
     */
    public function likeable()
    {
        return $this->morphTo();
    }
    
    // Keep the old relationships for backwards compatibility
    public function post() {
        return $this->belongsTo(Post::class, 'post_id');
    }

    public function comment() {
        return $this->belongsTo(Comment::class, 'comment_id');
    }

    public function course() {
        return $this->belongsTo(Course::class, 'course_id');
    }
}
