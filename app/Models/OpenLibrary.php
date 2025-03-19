<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpenLibrary extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'type',
        'thumbnail_url',
        'course_id',
        'criteria'
    ];

    protected $casts = [
        'criteria' => 'array',
    ];

    /**
     * Get the course associated with this library (if it's a course library)
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the content items in this library
     */
    public function contents()
    {
        return $this->hasMany(LibraryContent::class, 'library_id');
    }

    /**
     * Get course content in this library
     */
    public function courses()
    {
        return $this->morphedByMany(Course::class, 'content', 'library_content');
    }

    /**
     * Get post content in this library
     */
    public function posts()
    {
        return $this->morphedByMany(Post::class, 'content', 'library_content');
    }

    /**
     * Get the most relevant contents for this library
     */
    public function topContents($limit = 10)
    {
        return $this->contents()
            ->orderBy('relevance_score', 'desc')
            ->limit($limit)
            ->get();
    }
}