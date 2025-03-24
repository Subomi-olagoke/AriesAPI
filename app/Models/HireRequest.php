<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HireRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 
        'tutor_id', 
        'status', 
        'message',
        'topic',
        'medium',
        'duration',
        'rate_per_session',
        'currency',
        'google_meet_link',
        'scheduled_at',
        'session_ended_at',
        'payment_status',
        'transaction_reference'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'session_ended_at' => 'datetime',
        'rate_per_session' => 'decimal:2',
    ];

    // Existing relationships
    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function tutor()
    {
        return $this->belongsTo(User::class, 'tutor_id');
    }

    // New relationships and methods
    public function sessions()
    {
        return $this->hasMany(TutoringSession::class);
    }

    public function conversation()
    {
        return $this->hasOne(Conversation::class);
    }

    public function isScheduled()
    {
        return !is_null($this->scheduled_at);
    }

    public function isPaid()
    {
        return $this->payment_status === 'paid';
    }

    public function isActive()
    {
        return $this->status === 'accepted' && $this->isScheduled() && $this->isPaid();
    }

    public function canBeStarted()
    {
        return $this->isActive() && $this->scheduled_at->isFuture() && $this->scheduled_at->diffInMinutes(now()) <= 15;
    }
}