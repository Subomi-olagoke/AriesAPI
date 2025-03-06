<?php

namespace App\Models;

use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Course extends Model {
    use Searchable;
    use HasFactory;

    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'difficulty_level' => $this->difficulty_level
        ];
    }
    
    protected $fillable = [
        'title',
        'description',
        'thumbnail_url',
        'video_url',
        'file_url',
        'price',
        'duration_minutes',
        'difficulty_level',
        'topic_id',
        'user_id'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'learning_outcomes' => 'array',
        'prerequisites' => 'array',
        'completion_criteria' => 'array'
    ];

    public function user(){
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function educator() {
        return $this->belongsTo(Educators::class, 'educator_id', 'id');
    }

    public function likes() {
        return $this->hasMany(Like::class, 'course_id');
    }
    
    public function topic() {
        return $this->belongsTo(Topic::class);
    }

    /**
     * Get the sections for the course.
     */
    public function sections()
    {
        return $this->hasMany(CourseSection::class)->orderBy('order');
    }

    /**
     * Get all lessons for the course through sections.
     */
    public function lessons()
    {
        return $this->hasManyThrough(CourseLesson::class, CourseSection::class);
    }

    /**
     * Get the enrollments for the course.
     */
    public function enrollments()
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    /**
     * Get the enrolled users for the course.
     */
    public function enrolledUsers()
    {
        return $this->belongsToMany(User::class, 'course_enrollments')
                ->withPivot('status', 'progress', 'transaction_reference')
                ->withTimestamps();
    }

    /**
     * Check if a user is enrolled in this course.
     */
    public function isUserEnrolled(User $user)
    {
        return $this->enrollments()
                ->where('user_id', $user->id)
                ->whereIn('status', ['active', 'completed'])
                ->exists();
    }

    /**
     * Get total duration of all course lessons.
     */
    public function getTotalDurationAttribute()
    {
        return $this->lessons()->sum('duration_minutes');
    }

    /**
     * Get number of lessons in the course.
     */
    public function getLessonCountAttribute()
    {
        return $this->lessons()->count();
    }

    /**
     * Get active enrollment count.
     */
    public function getActiveEnrollmentsCountAttribute()
    {
        return $this->enrollments()->where('status', 'active')->count();
    }
    
    /**
     * Get total enrollment count (active and completed).
     */
    public function getEnrollmentsCountAttribute()
    {
        return $this->enrollments()
                ->whereIn('status', ['active', 'completed'])
                ->count();
    }
    
    /**
     * Get total revenue generated from this course.
     */
    public function getRevenueAttribute()
    {
        // Only count active and completed enrollments for revenue
        return $this->enrollments()
                ->whereIn('status', ['active', 'completed'])
                ->count() * $this->price;
    }

    /**
     * Get preview lessons for this course.
     */
    public function getPreviewLessonsAttribute()
    {
        return $this->lessons()->where('is_preview', true)->get();
    }
    
    /**
     * Scope a query to only include free courses.
     */
    public function scopeFree($query)
    {
        return $query->where('price', 0);
    }
    
    /**
     * Scope a query to only include paid courses.
     */
    public function scopePaid($query)
    {
        return $query->where('price', '>', 0);
    }
    
    /**
     * Scope a query to filter by difficulty level
     */
    public function scopeByDifficulty($query, $level)
    {
        return $query->where('difficulty_level', $level);
    }
    
    /**
     * Scope a query to order by popularity (enrollment count).
     */
    public function scopePopular($query)
    {
        return $query->withCount(['enrollments' => function($query) {
            $query->whereIn('status', ['active', 'completed']);
        }])->orderBy('enrollments_count', 'desc');
    }
    
    /**
     * Get the readlist items for this course.
     */
    public function readlistItems()
    {
        return $this->morphMany(ReadlistItem::class, 'item');
    }
}