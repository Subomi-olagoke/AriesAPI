<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model {
	protected $fillable = [
		'title',
		'description',
		'video_url',
		'price',
	];

	use HasFactory;

    public function user(){
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function educator() {
        return $this->belongsTo(Educators::class, 'educator_id', 'id');
    }

    public function likes() {
        return $this->hasMany(Like::class, 'course_id');
    }

}
