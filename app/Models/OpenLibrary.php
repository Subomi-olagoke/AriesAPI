<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpenLibrary extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'type',
        'thumbnail_url',
        'cover_image_url',
        'course_id',
        'criteria',
        'keywords',
        'is_approved',
        'approval_status',
        'approval_date',
        'approved_by',
        'rejection_reason',
        'cover_prompt',
        'ai_generated',
        'ai_generation_date',
        'ai_model_used',
        'has_ai_cover'
    ];

    protected $casts = [
        'criteria' => 'array',
        'keywords' => 'array',
        'is_approved' => 'boolean',
        'has_ai_cover' => 'boolean',
        'ai_generated' => 'boolean',
        'approval_date' => 'datetime',
        'ai_generation_date' => 'datetime',
    ];

    /**
     * Get the course associated with this library (if it's a course library)
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the content items in this library
     */
    public function contents()
    {
        return $this->hasMany(LibraryContent::class, 'library_id');
    }

    /**
     * Get course content in this library
     */
    public function courses()
    {
        return $this->morphedByMany(Course::class, 'content', 'library_content');
    }

    /**
     * Get post content in this library
     */
    public function posts()
    {
        return $this->morphedByMany(Post::class, 'content', 'library_content');
    }

    /**
     * Get the most relevant contents for this library
     */
    public function topContents($limit = 10)
    {
        return $this->contents()
            ->orderBy('relevance_score', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Get the user who approved this library
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
    
    /**
     * Scope for approved libraries
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true)
                    ->where('approval_status', 'approved');
    }
    
    /**
     * Scope for pending libraries
     */
    public function scopePending($query)
    {
        return $query->where('approval_status', 'pending');
    }
    
    /**
     * Scope for rejected libraries
     */
    public function scopeRejected($query)
    {
        return $query->where('approval_status', 'rejected');
    }
}