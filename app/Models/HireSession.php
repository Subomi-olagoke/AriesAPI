<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HireSession extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'hire_request_id',
        'google_meet_link',
        'scheduled_at',
        'ended_at',
        'duration_minutes',
        'status',
        'payment_status',
        'transaction_reference',
        'meeting_id',
        'video_session_started_at',
        'video_session_ended_at',
        'video_session_status',
        'video_session_settings',
        'recording_url'
    ];
    
    protected $casts = [
        'scheduled_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_minutes' => 'integer',
        'video_session_started_at' => 'datetime',
        'video_session_ended_at' => 'datetime',
        'video_session_settings' => 'array',
    ];
    
    /**
     * Get the hire request that this session belongs to.
     */
    public function hireRequest()
    {
        return $this->belongsTo(HireRequest::class);
    }
    
    /**
     * Get the ratings for this session.
     */
    public function ratings()
    {
        return $this->hasMany(EducatorRating::class);
    }
    
    /**
     * Get the user who requested the session (client).
     */
    public function client()
    {
        return $this->hireRequest->client();
    }
    
    /**
     * Get the educator for this session.
     */
    public function educator()
    {
        return $this->hireRequest->tutor();
    }
    
    /**
     * Check if the session is completed.
     */
    public function isCompleted()
    {
        return $this->status === 'completed';
    }
    
    /**
     * Check if the session has been rated by the client.
     */
    public function isRated()
    {
        return $this->ratings()->where('user_id', $this->client()->id)->exists();
    }
    
    /**
     * Get the conversation associated with this hire session.
     */
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
    
    /**
     * Check if messaging is allowed for this session.
     */
    public function canMessage()
    {
        return $this->can_message;
    }
    
    /**
     * Enable messaging for this session.
     */
    public function enableMessaging()
    {
        $this->can_message = true;
        return $this->save();
    }
    
    /**
     * Disable messaging for this session.
     */
    public function disableMessaging()
    {
        $this->can_message = false;
        return $this->save();
    }
    
    /**
     * Get the documents shared in this session.
     */
    public function documents()
    {
        return $this->hasMany(HireSessionDocument::class)->orderBy('shared_at', 'desc');
    }
    
    /**
     * Get the participants in this video session.
     */
    public function participants()
    {
        return $this->hasMany(HireSessionParticipant::class);
    }
    
    /**
     * Get only active participants (who haven't left).
     */
    public function activeParticipants()
    {
        return $this->participants()->whereNull('left_at');
    }
    
    /**
     * Check if a user is part of this session.
     */
    public function isParticipant(User $user)
    {
        return $this->hireRequest->client_id === $user->id || 
               $this->hireRequest->tutor_id === $user->id;
    }
    
    /**
     * Generate a unique meeting ID for the video session.
     */
    public static function generateMeetingId()
    {
        return strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    }
    
    /**
     * Start a video session if not already started.
     */
    public function startVideoSession($settings = null)
    {
        if ($this->video_session_status !== 'active') {
            $defaultSettings = [
                'enable_chat' => true,
                'mute_on_join' => false,
                'video_on_join' => true,
                'allow_screen_sharing' => true,
                'recording_enabled' => false
            ];
            
            $this->update([
                'meeting_id' => $this->meeting_id ?: self::generateMeetingId(),
                'video_session_started_at' => now(),
                'video_session_status' => 'active',
                'video_session_settings' => $settings ?: $defaultSettings
            ]);
        }
        
        return $this;
    }
    
    /**
     * End a video session if it's active.
     */
    public function endVideoSession()
    {
        if ($this->video_session_status === 'active') {
            $this->update([
                'video_session_ended_at' => now(),
                'video_session_status' => 'ended'
            ]);
            
            // Mark all active participants as left
            $this->activeParticipants()->update(['left_at' => now()]);
        }
        
        return $this;
    }
    
    /**
     * Check if the video session is currently active.
     */
    public function isVideoSessionActive()
    {
        return $this->video_session_status === 'active';
    }
    
    /**
     * Get all participants with their user data.
     */
    public function getParticipantsWithUsers()
    {
        return $this->participants()
            ->with('user:id,first_name,last_name,username,avatar,role')
            ->get();
    }
    
    /**
     * Get only active participants with their user data.
     */
    public function getActiveParticipantsWithUsers()
    {
        return $this->activeParticipants()
            ->with('user:id,first_name,last_name,username,avatar,role')
            ->get();
    }
}
