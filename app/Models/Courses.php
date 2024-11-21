<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Courses extends Model {
	protected $fillable = [
		'title',
		'description',
		'video_url',
		'price',
	];

	use HasFactory;

    public function user(){
        return $this->hasMany(User::class, 'user_id', 'id');
    }

    public function educator() {
        return $this->hasMany(Educators::class, 'educator_id');
    }
}
