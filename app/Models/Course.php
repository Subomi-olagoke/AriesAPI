<?php

namespace App\Models;

use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Course extends Model {
    use Searchable;

    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
        ];
    }
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
