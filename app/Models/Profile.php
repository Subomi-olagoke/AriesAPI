<?php

namespace App\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model {
	protected $fillable = ['bio', 'avatar'];
	public function User() {
		return $this->belongsTo(User::class, 'user_id', 'id');
	}
}
