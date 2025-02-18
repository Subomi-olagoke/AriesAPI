<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveClass extends Model
{
    protected $fillable = [
        'title',
        'description',
        'teacher_id',
        'scheduled_at',
        'ended_at',
        'status',
        'meeting_id',
        'settings'
    ];

    protected $casts = [
        'settings' => 'array',
        'scheduled_at' => 'datetime',
        'ended_at' => 'datetime'
    ];

    public static function generateMeetingId()
    {
        return strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function participants()
    {
        return $this->hasMany(LiveClassParticipant::class);
    }

    public function activeParticipants()
    {
        return $this->participants()->whereNull('left_at');
    }
}
