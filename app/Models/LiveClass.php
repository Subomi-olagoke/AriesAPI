<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
    
    /**
     * Check if this live class is expired (past its end date).
     */
    public function isExpired()
    {
        return $this->ended_at && $this->ended_at->isPast();
    }
    
    /**
     * Scope a query to only include expired live classes.
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('ended_at')
                    ->where('ended_at', '<', now());
    }
    
    /**
     * Clean up expired live classes and their related data.
     * 
     * @param int $daysOld Number of days after end date to wait before cleanup (default: 1)
     * @return int Number of classes cleaned up
     */
    public static function cleanupExpired($daysOld = 1)
    {
        $cutoffDate = now()->subDays($daysOld);
        
        $expiredClasses = self::whereNotNull('ended_at')
            ->where('ended_at', '<', $cutoffDate)
            ->get();
            
        $cleanedCount = 0;
        
        foreach ($expiredClasses as $class) {
            try {
                DB::transaction(function () use ($class) {
                    // Delete related chat messages
                    $class->chatMessages()->delete();
                    
                    // Delete participants
                    $class->participants()->delete();
                    
                    // Delete the live class itself
                    $class->delete();
                });
                
                $cleanedCount++;
                Log::info('Cleaned up expired live class', [
                    'class_id' => $class->id,
                    'title' => $class->title,
                    'ended_at' => $class->ended_at
                ]);
                
            } catch (\Exception $e) {
                Log::error('Failed to cleanup live class', [
                    'class_id' => $class->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $cleanedCount;
    }
}