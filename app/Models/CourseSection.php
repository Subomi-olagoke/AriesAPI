<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'title',
        'description',
        'order'
    ];

    /**
     * Get the course that owns the section.
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the lessons for the section.
     */
    public function lessons()
    {
        return $this->hasMany(CourseLesson::class, 'section_id')->orderBy('order');
    }

    /**
     * Get total duration of all lessons in this section.
     */
    public function getDurationAttribute()
    {
        return $this->lessons()->sum('duration_minutes');
    }

    /**
     * Get number of lessons in the section.
     */
    public function getLessonCountAttribute()
    {
        return $this->lessons()->count();
    }

    /**
     * Check if section has any lessons marked as preview.
     */
    public function getHasPreviewAttribute()
    {
        return $this->lessons()->where('is_preview', true)->exists();
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Automatically assign order when creating a new section
        static::creating(function ($section) {
            if (is_null($section->order)) {
                $maxOrder = static::where('course_id', $section->course_id)->max('order');
                $section->order = is_null($maxOrder) ? 0 : $maxOrder + 1;
            }
        });
    }
}