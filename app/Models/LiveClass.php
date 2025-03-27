<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveClass extends Model
{
    protected $fillable = [
        'title',
        'description',
        'teacher_id',
        'course_id',          // Added course_id
        'lesson_id',          // Added specific lesson_id (optional)
        'scheduled_at',
        'ended_at',
        'status',
        'meeting_id',
        'settings',
        'recording_url',      // For future recording feature
        'class_type'          // 'course-related' or 'standalone'
    ];

    protected $casts = [
        'settings' => 'array',
        'scheduled_at' => 'datetime',
        'ended_at' => 'datetime'
    ];

    public static function generateMeetingId()
    {
        return strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function participants()
    {
        return $this->hasMany(LiveClassParticipant::class);
    }

    public function activeParticipants()
    {
        return $this->participants()->whereNull('left_at');
    }
    
    /**
     * Get the course this live class is associated with.
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }
    
    /**
     * Get the specific lesson this live class is associated with.
     */
    public function lesson()
    {
        return $this->belongsTo(CourseLesson::class, 'lesson_id');
    }
    
    /**
     * Get chat messages for this live class.
     */
    public function chatMessages()
    {
        return $this->hasMany(LiveClassChat::class);
    }
    
    /**
     * Scope a query to only include course-related live classes.
     */
    public function scopeCourseRelated($query)
    {
        return $query->whereNotNull('course_id');
    }
    
    /**
     * Scope a query to only include standalone live classes.
     */
    public function scopeStandalone($query)
    {
        return $query->whereNull('course_id');
    }
    
    /**
     * Check if this live class is associated with a course.
     */
    public function isCourseRelated()
    {
        return !is_null($this->course_id);
    }
    
    /**
     * Check if this live class is scheduled for the future.
     */
    public function isScheduled()
    {
        return $this->scheduled_at->isFuture();
    }
    
    /**
     * Check if this live class is currently active.
     */
    public function isActive()
    {
        return $this->status === 'live';
    }
    
    /**
     * Check if this live class has ended.
     */
    public function hasEnded()
    {
        return $this->status === 'ended';
    }
}