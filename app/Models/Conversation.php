<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Conversation extends Model
{
    use HasFactory;

    // Disable auto-incrementing ID
    public $incrementing = false;
    
    // Set ID type to string
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_one_id',
        'user_two_id',
        'last_message_at',
        'is_archived'
    ];

    protected $casts = [
        'id' => 'string',
        'last_message_at' => 'datetime',
        'is_archived' => 'boolean',
    ];

    // Generate ID based on user IDs when creating a new conversation
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->id)) {
                // Sort user IDs to ensure consistency regardless of who initiates the conversation
                $userIds = [$model->user_one_id, $model->user_two_id];
                sort($userIds);
                
                // Combine them to create a unique but predictable ID
                $model->id = implode('_', $userIds);
            }
        });
    }

    /**
     * Get the first user in the conversation.
     */
    public function userOne()
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    /**
     * Get the second user in the conversation.
     */
    public function userTwo()
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    /**
     * Get the messages in the conversation.
     */
    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    /**
     * Get the latest message in the conversation.
     */
    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latest();
    }

    /**
     * Get the other user in the conversation.
     */
    public function getOtherUser(User $user)
    {
        return $user->id === $this->user_one_id 
            ? User::findOrFail($this->user_two_id) 
            : User::findOrFail($this->user_one_id);
    }

    /**
     * Get unread messages for a user.
     */
    public function unreadMessagesFor(User $user)
    {
        return $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->count();
    }
    
    /**
     * Get the hire session associated with this conversation.
     */
    public function hireSession()
    {
        return $this->belongsTo(HireSession::class);
    }
    
    /**
     * Check if this conversation is restricted to users with an active hire session.
     */
    public function isRestricted()
    {
        return $this->is_restricted;
    }
    
    /**
     * Check if a user can send messages in this conversation.
     */
    public function canSendMessages(User $user)
    {
        // If conversation is not restricted, anyone can send messages
        if (!$this->is_restricted) {
            return true;
        }
        
        // If no hire session, check if user is an admin
        if (!$this->hire_session_id) {
            return $user->isAdmin;
        }
        
        // Get the other user
        $otherUser = $this->getOtherUser($user);
        
        // If user is a learner and other user is an educator, check if they can message
        if ($user->role === User::ROLE_LEARNER && $otherUser->role === User::ROLE_EDUCATOR) {
            return $user->canMessageEducator($otherUser);
        }
        
        // If user is an educator and other user is a learner, check the hire session
        if ($user->role === User::ROLE_EDUCATOR && $otherUser->role === User::ROLE_LEARNER) {
            return HireSession::where([
                'educator_id' => $user->id,
                'learner_id' => $otherUser->id,
                'status' => 'active',
                'can_message' => true
            ])->exists();
        }
        
        // Default to allowing messages
        return true;
    }
}