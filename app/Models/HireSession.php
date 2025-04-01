<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HireSession extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'hire_request_id',
        'google_meet_link',
        'scheduled_at',
        'ended_at',
        'duration_minutes',
        'status',
        'payment_status',
        'transaction_reference'
    ];
    
    protected $casts = [
        'scheduled_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_minutes' => 'integer',
    ];
    
    /**
     * Get the hire request that this session belongs to.
     */
    public function hireRequest()
    {
        return $this->belongsTo(HireRequest::class);
    }
    
    /**
     * Get the ratings for this session.
     */
    public function ratings()
    {
        return $this->hasMany(EducatorRating::class);
    }
    
    /**
     * Get the user who requested the session (client).
     */
    public function client()
    {
        return $this->hireRequest->client();
    }
    
    /**
     * Get the educator for this session.
     */
    public function educator()
    {
        return $this->hireRequest->tutor();
    }
    
    /**
     * Check if the session is completed.
     */
    public function isCompleted()
    {
        return $this->status === 'completed';
    }
    
    /**
     * Check if the session has been rated by the client.
     */
    public function isRated()
    {
        return $this->ratings()->where('user_id', $this->client()->id)->exists();
    }
}
