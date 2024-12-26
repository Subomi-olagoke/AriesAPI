<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Educators extends Model {
	use HasFactory;

	protected $fillable = ['bio', 'avatar', 'courses', 'posts'];

	public function educator() {
		return $this->belongsTo(User::class);
	}

	public function courses() {
		return $this->
        hasMany(User::class, 'user_id', );
	}

    public function user() {

    }
}
