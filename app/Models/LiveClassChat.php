<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiveClassChat extends Model
{
    use HasFactory;

    protected $fillable = [
        'live_class_id',
        'user_id',
        'message',
        'type', // 'text', 'system', 'file', etc.
        'metadata' // JSON field for additional information
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the live class that owns the chat message.
     */
    public function liveClass()
    {
        return $this->belongsTo(LiveClass::class);
    }

    /**
     * Get the user who sent the message.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}