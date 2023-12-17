<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Educators extends Model {
	use HasFactory;

	protected $fillable = ['bio', 'avatar', 'courses', 'posts']; // Add more fields as needed

	public function educator() {
		return $this->belongsTo(Educators::class);
	}

	public function courses() {
		return $this->hasMany(Courses::class, 'user_id');
	}

}
