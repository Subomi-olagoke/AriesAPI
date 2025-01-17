<?php

namespace App\Models;

use App\Models\User;
use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Post extends Model {
    use Searchable;

    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
        ];
    }

	use HasFactory;


	protected $fillable = ['title', 'body', 'user_id', 'media_link', 'media_type'];

	public function user() {
		return $this->belongsTo(User::class, 'user_id', 'id');
	}

    public function comments() {
        return $this->hasMany(Comment::class, 'post_id');
    }


}
