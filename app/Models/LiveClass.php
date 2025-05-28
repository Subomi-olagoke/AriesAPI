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
     * Check if this live class is overdue (past its scheduled time and not started).
     */
    public function isOverdue()
    {
        return $this->scheduled_at && 
               $this->scheduled_at->isPast() && 
               $this->status !== 'live' && 
               $this->status !== 'ended';
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
     * Scope a query to only include overdue live classes.
     */
    public function scopeOverdue($query)
    {
        return $query->whereNotNull('scheduled_at')
                    ->where('scheduled_at', '<', now())
                    ->whereNotIn('status', ['live', 'ended']);
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
    
    /**
     * Clean up overdue live classes (classes that passed their scheduled time but never started).
     * 
     * @param int $hoursOverdue Number of hours past scheduled time to wait before cleanup (default: 1)
     * @return int Number of classes cleaned up
     */
    public static function cleanupOverdue($hoursOverdue = 1)
    {
        $cutoffTime = now()->subHours($hoursOverdue);
        
        $overdueClasses = self::whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', $cutoffTime)
            ->whereNotIn('status', ['live', 'ended'])
            ->get();
            
        $cleanedCount = 0;
        
        foreach ($overdueClasses as $class) {
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
                Log::info('Cleaned up overdue live class', [
                    'class_id' => $class->id,
                    'title' => $class->title,
                    'scheduled_at' => $class->scheduled_at,
                    'status' => $class->status
                ]);
                
            } catch (\Exception $e) {
                Log::error('Failed to cleanup overdue live class', [
                    'class_id' => $class->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $cleanedCount;
    }
    
    /**
     * Clean up both expired and overdue live classes.
     * 
     * @param int $daysOld Number of days after end date to wait before cleanup (default: 1)
     * @param int $hoursOverdue Number of hours past scheduled time to wait before cleanup (default: 1)
     * @return array Cleanup results
     */
    public static function cleanupAll($daysOld = 1, $hoursOverdue = 1)
    {
        $expiredCleaned = self::cleanupExpired($daysOld);
        $overdueCleaned = self::cleanupOverdue($hoursOverdue);
        
        return [
            'expired_cleaned' => $expiredCleaned,
            'overdue_cleaned' => $overdueCleaned,
            'total_cleaned' => $expiredCleaned + $overdueCleaned
        ];
    }
}