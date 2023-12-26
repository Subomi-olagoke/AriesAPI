<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $fillable = ['bio', 'avatar']; // Add more fields as needed

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function posts() {
        return $this->hasMany(Courses::class, 'user_id');
    }
}
