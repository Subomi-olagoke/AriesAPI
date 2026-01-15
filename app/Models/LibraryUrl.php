<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LibraryUrl extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'url',
        'title',
        'summary',
        'notes',
        'thumbnail_url',
        'created_by',
        'readlist_count'
    ];

    /**
     * The libraries that this URL belongs to.
     */
    public function libraries()
    {
        return $this->morphToMany(OpenLibrary::class, 'content', 'library_content', 'content_id', 'library_id');
    }

    /**
     * Get the primary/first library this URL belongs to.
     * Used for display purposes when showing user entries.
     */
    public function library()
    {
        return $this->morphToMany(OpenLibrary::class, 'content', 'library_content', 'content_id', 'library_id')->limit(1);
    }


    /**
     * The user who created this URL.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Alias for creator() to maintain consistency with other content models.
     */
    public function user()
    {
        return $this->creator();
    }

    /**
     * Get all comments for this library URL.
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(\App\Models\Comment::class, 'commentable');
    }
}