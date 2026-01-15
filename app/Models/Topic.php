<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Topic extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Get the users that have this topic.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_topic', 'topic_id', 'user_id');
    }
}



