<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_one_id',
        'user_two_id',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

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
        if ($user->id === $this->user_one_id) {
            return User::find($this->user_two_id);
        }

        return User::find($this->user_one_id);
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
}
