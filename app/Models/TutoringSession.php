<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TutoringSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'hire_request_id',
        'google_meet_link',
        'scheduled_at',
        'ended_at',
        'duration_minutes',
        'status', // scheduled, in_progress, completed, canceled
        'feedback_rating',
        'feedback_comment',
        'payment_status',
        'transaction_reference'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'ended_at' => 'datetime',
        'feedback_rating' => 'integer',
    ];

    public function hireRequest()
    {
        return $this->belongsTo(HireRequest::class);
    }

    public function client()
    {
        return $this->hireRequest->client();
    }

    public function tutor()
    {
        return $this->hireRequest->tutor();
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isCanceled()
    {
        return $this->status === 'canceled';
    }

    public function isPaid()
    {
        return $this->payment_status === 'paid';
    }
}