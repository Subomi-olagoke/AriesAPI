<?php

namespace App\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model {
	protected $fillable = ['bio', 'avatar'];
	// public function user() {
	// 	return $this->belongsTo(User::class, 'user_id');
	// }

	// public function posts() {
	// 	return $this->hasMany(Courses::class, 'user_id');
	// }
}
