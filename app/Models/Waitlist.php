<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Waitlist extends Model
{
    use HasFactory;

    protected $table = 'waitlist';
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
    ];

    /**
     * Get the email records sent to this waitlist entry.
     */
    public function emails()
    {
        return $this->belongsToMany(WaitlistEmail::class, 'waitlist_email_recipients', 'waitlist_id', 'waitlist_email_id')
            ->withPivot('sent_at')
            ->withTimestamps();
    }
}