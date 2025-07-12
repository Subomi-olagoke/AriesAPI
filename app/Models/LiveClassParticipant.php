<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveClassParticipant extends Model
{
    protected $fillable = [
        'user_id',
        'live_class_id',
        'role',
        'preferences',
        'joined_at',
        'left_at',
        'hand_raised',
        'hand_raised_at'
    ];

    protected $casts = [
        'preferences' => 'array',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'hand_raised' => 'boolean',
        'hand_raised_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function liveClass()
    {
        return $this->belongsTo(LiveClass::class);
    }
}
