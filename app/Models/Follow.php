<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Follow extends Model
{
    use HasFactory;

    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function userDoingTheFollowing() {
        return $this->belongsToMany(User::class, 'user_id');
    }

    public function userBeingFollowed() {
        return $this->belongsToMany(User::class, 'followeduser');
    }
}
