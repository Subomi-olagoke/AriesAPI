<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Schema;

class Course extends Model {
    
    use HasFactory;

    
    
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
        'user_id',
        'is_featured',
        'average_rating',
        'total_ratings'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'learning_outcomes' => 'array',
        'prerequisites' => 'array',
        'completion_criteria' => 'array',
        'is_featured' => 'boolean',
        'average_rating' => 'decimal:2',
        'total_ratings' => 'integer'
    ];

    public function user(){
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function educator() {
        return $this->belongsTo(Educators::class, 'educator_id', 'id');
    }

    public function likes() {
        // For backward compatibility, check if the likeable_type column exists
        if (Schema::hasColumn('likes', 'likeable_type')) {
            return $this->morphMany(Like::class, 'likeable');
        } else {
            return $this->hasMany(Like::class, 'course_id');
        }
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
     * Scope a query to only include featured courses.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }
    
    /**
     * Get the readlist items for this course.
     */
    public function readlistItems()
    {
        return $this->morphMany(ReadlistItem::class, 'item');
    }

    /**
     * Get the ratings for this course.
     */
    public function ratings()
    {
        return $this->hasMany(CourseRating::class);
    }

    /**
     * Get the average rating for this course.
     */
    public function getAverageRatingAttribute($value)
    {
        if ($this->total_ratings > 0) {
            return round($value, 1);
        }
        return 0.0;
    }

    /**
     * Check if a user has rated this course.
     */
    public function hasUserRated(User $user)
    {
        return $this->ratings()->where('user_id', $user->id)->exists();
    }

    /**
     * Get a user's rating for this course.
     */
    public function getUserRating(User $user)
    {
        return $this->ratings()->where('user_id', $user->id)->first();
    }

    /**
     * Update course rating statistics.
     */
    public function updateRatingStats()
    {
        $ratings = $this->ratings();
        $totalRatings = $ratings->count();
        
        if ($totalRatings > 0) {
            $averageRating = $ratings->avg('rating');
            $this->update([
                'average_rating' => round($averageRating, 2),
                'total_ratings' => $totalRatings
            ]);
        } else {
            $this->update([
                'average_rating' => 0.00,
                'total_ratings' => 0
            ]);
        }
    }
}