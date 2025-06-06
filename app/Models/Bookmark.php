<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bookmark extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'course_id', 'post_id', 'open_library_id'];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function course() {
        return $this->belongsTo(Course::class);
    }

    public function post() {
        return $this->belongsTo(Post::class);
    }
    
    public function openLibrary() {
        return $this->belongsTo(OpenLibrary::class);
    }
}
