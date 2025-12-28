<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EducatorRating extends Model
{
    use HasFactory;

    protected $table = 'educator_ratings';

    protected $fillable = [
        'educator_id',
        'student_id',
        'rating',
        'review',
        'course_id',
    ];

    public $timestamps = true;

    protected $casts = [
        'rating' => 'decimal:2',
    ];

    /**
     * Get the educator being rated
     */
    public function educator()
    {
        return $this->belongsTo(User::class, 'educator_id');
    }

    /**
     * Get the student who gave the rating
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Get the course related to this rating (if applicable)
     */
    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }
}
