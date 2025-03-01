<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'transaction_reference',
        'status',
        'progress'
    ];

    protected $casts = [
        'progress' => 'decimal:2',
    ];

    /**
     * Get the user that enrolled in the course.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the course the user is enrolled in.
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Scope a query to only include active enrollments.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include completed enrollments.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Update the progress of the enrollment.
     */
    public function updateProgress($newProgress)
    {
        $this->progress = $newProgress;
        
        // If progress is 100%, mark as completed
        if ($this->progress >= 100) {
            $this->status = 'completed';
            $this->progress = 100;
        }
        
        return $this->save();
    }

    /**
     * Activate the enrollment.
     */
    public function activate()
    {
        $this->status = 'active';
        return $this->save();
    }

    /**
     * Cancel the enrollment.
     */
    public function cancel()
    {
        $this->status = 'cancelled';
        return $this->save();
    }
}