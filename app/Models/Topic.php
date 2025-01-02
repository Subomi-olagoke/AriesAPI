<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topic extends Model
{
    use HasFactory;

    public function user() {
        return $this->belongsToMany(User::class, 'user_topic', 'topic_id', 'user_id');
    }

    public function courses() {
        return $this->hasMany(Course::class, 'topic_id');
    }
}
