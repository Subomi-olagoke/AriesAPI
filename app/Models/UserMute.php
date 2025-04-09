<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserMute extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'muted_user_id',
    ];

    /**
     * Get the user who created the mute.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user who has been muted.
     */
    public function mutedUser()
    {
        return $this->belongsTo(User::class, 'muted_user_id');
    }
}