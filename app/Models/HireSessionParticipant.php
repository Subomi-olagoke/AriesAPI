<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HireSessionParticipant extends Model
{
    use HasFactory;
    
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;
    
    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'hire_session_id',
        'user_id',
        'role',
        'joined_at',
        'left_at',
        'preferences',
        'connection_quality'
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'string',
        'hire_session_id' => 'string',
        'user_id' => 'string',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'preferences' => 'array'
    ];
    
    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Str::uuid()->toString();
            }
            
            // Set default preferences if not provided
            if (empty($model->preferences)) {
                $model->preferences = [
                    'video' => true,
                    'audio' => true,
                    'screen_share' => false
                ];
            }
        });
    }
    
    /**
     * Get the hire session that this participant belongs to.
     */
    public function hireSession()
    {
        return $this->belongsTo(HireSession::class);
    }
    
    /**
     * Get the user for this participation.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Check if this participant is a moderator.
     */
    public function isModerator()
    {
        return $this->role === 'moderator';
    }
    
    /**
     * Check if this participant is still active (hasn't left).
     */
    public function isActive()
    {
        return $this->left_at === null;
    }
    
    /**
     * Check if this participant has video enabled.
     */
    public function hasVideoEnabled()
    {
        return $this->preferences['video'] ?? false;
    }
    
    /**
     * Check if this participant has audio enabled.
     */
    public function hasAudioEnabled()
    {
        return $this->preferences['audio'] ?? false;
    }
    
    /**
     * Check if this participant has screen sharing enabled.
     */
    public function hasScreenShareEnabled()
    {
        return $this->preferences['screen_share'] ?? false;
    }
}
