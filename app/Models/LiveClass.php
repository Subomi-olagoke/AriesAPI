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
        'status'
    ];

    protected $dates = [
        'scheduled_at',
        'ended_at'
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
