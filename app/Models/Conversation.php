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

    // Automatically generate UUID when creating a new conversation
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Str::uuid()->toString();
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
}