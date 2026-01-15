<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
    use HasFactory;
    
    protected $fillable = ['user_id', 'voteable_id', 'voteable_type', 'vote_type'];
    
    /**
     * Get the parent voteable model (LibraryUrl, Post, etc).
     */
    public function voteable()
    {
        return $this->morphTo();
    }
    
    /**
     * Get the user who cast the vote.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    /**
     * Check if vote is an upvote
     */
    public function isUpvote()
    {
        return $this->vote_type === 'up';
    }
    
    /**
     * Check if vote is a downvote
     */
    public function isDownvote()
    {
        return $this->vote_type === 'down';
    }
}

