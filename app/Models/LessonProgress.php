<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LessonProgress extends Model
{
    use HasFactory;
    
    protected $table = 'lesson_progress';
    
    protected $fillable = [
        'user_id',
        'lesson_id',
        'completed',
        'watched_seconds',
        'last_watched_at',
        'quiz_answers',
        'assignment_submission'
    ];
    
    protected $casts = [
        'completed' => 'boolean',
        'watched_seconds' => 'integer',
        'last_watched_at' => 'datetime',
        'quiz_answers' => 'array',
        'assignment_submission' => 'array'
    ];
    
    /**
     * Get the user that owns the progress record.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Get the lesson that this progress record belongs to.
     */
    public function lesson()
    {
        return $this->belongsTo(CourseLesson::class, 'lesson_id');
    }
}