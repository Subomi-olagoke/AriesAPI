<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseLesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_id',
        'title',
        'description',
        'video_url',
        'file_url',
        'thumbnail_url',
        'content_type',
        'duration_minutes',
        'order',
        'quiz_data',
        'assignment_data',
        'is_preview'
    ];

    protected $casts = [
        'quiz_data' => 'array',
        'assignment_data' => 'array',
        'is_preview' => 'boolean',
        'duration_minutes' => 'integer'
    ];

    /**
     * Get the section that owns the lesson.
     */
    public function section()
    {
        return $this->belongsTo(CourseSection::class, 'section_id');
    }

    /**
     * Get the course through the section.
     */
    public function course()
    {
        return $this->hasOneThrough(
            Course::class,
            CourseSection::class,
            'id', // Foreign key on course_sections table
            'id', // Foreign key on courses table
            'section_id', // Local key on course_lessons table
            'course_id' // Local key on course_sections table
        );
    }

    /**
     * Get user progress records for this lesson.
     */
    public function progress()
    {
        return $this->hasMany(LessonProgress::class, 'lesson_id');
    }

    /**
     * Check if a user has completed this lesson.
     */
    public function isCompletedBy(User $user)
    {
        return $this->progress()
            ->where('user_id', $user->id)
            ->where('completed', true)
            ->exists();
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Automatically assign order when creating a new lesson
        static::creating(function ($lesson) {
            if (is_null($lesson->order)) {
                $maxOrder = static::where('section_id', $lesson->section_id)->max('order');
                $lesson->order = is_null($maxOrder) ? 0 : $maxOrder + 1;
            }
        });
    }
}