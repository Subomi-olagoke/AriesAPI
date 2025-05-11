<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaitlistEmail extends Model
{
    use HasFactory;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'subject',
        'content',
        'sent_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'sent_at' => 'datetime',
    ];

    /**
     * Get the waitlist entries this email was sent to.
     */
    public function recipients()
    {
        return $this->belongsToMany(Waitlist::class, 'waitlist_email_recipients', 'waitlist_email_id', 'waitlist_id')
            ->withPivot('sent_at')
            ->withTimestamps();
    }
}