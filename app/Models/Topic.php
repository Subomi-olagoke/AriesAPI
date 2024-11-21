<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topic extends Model
{
    use HasFactory;

    public function user() {
        return $this->belongsToMany(User::class, 'user_id', 'id');
    }

    public function course() {
        return $this->hasMany(Courses::class, 'course_id');
    }
}
