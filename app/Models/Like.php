<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Like extends Model
{
    use HasFactory;
    protected $fillable = ['user_id','likeable_id', 'likeable_type'];

    public function likeable()
    {
        return $this->morphTo();
    }

    public function user() {
		return $this->belongsTo(User::class, 'user_id');
	}

    // public function post() {
    //     return $this->belongsTo(Post::class, 'likeable_id');
    // }

    // public function comments() {
    //     return $this->belongsTo(comments::class, 'comment_id');
    // }

}
