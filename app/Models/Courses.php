<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Courses extends Model {
	protected $fillable = [
		'title',
		'description',
		'video_url', // Add this line
		'price',
	];

	use HasFactory;
}
