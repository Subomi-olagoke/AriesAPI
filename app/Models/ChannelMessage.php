<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Notifications\MentionNotification;

class ChannelMessage extends Model
{
    use HasFactory, SoftDeletes;
    
    // Disable auto-incrementing ID
    public $incrementing = false;
    
    // Set ID type to string (for UUID)
    protected $keyType = 'string';
    
    protected $fillable = [
        'id',
        'channel_id',
        'sender_id',
        'body',
        'attachment',
        'attachment_type',
        'read_by'
    ];
    
    protected $casts = [
        'read_by' => 'array'
    ];
    
    // Automatically generate UUID when creating a new message
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Str::uuid()->toString();
            }
            
            if (empty($model->read_by)) {
                $model->read_by = [$model->sender_id];
            }
        });
    }
    
    /**
     * Get the channel that owns the message.
     */
    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }
    
    /**
     * Get the sender of the message.
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
    
    /**
     * Mark this message as read by a user.
     */
    public function markAsReadBy(User $user)
    {
        if (!in_array($user->id, (array) $this->read_by)) {
            $readBy = (array) $this->read_by;
            $readBy[] = $user->id;
            $this->update(['read_by' => $readBy]);
        }
    }
    
    /**
     * Check if this message has been read by a user.
     */
    public function isReadBy(User $user)
    {
        return in_array($user->id, (array) $this->read_by);
    }
    
    /**
     * Get mentions in this message.
     */
    public function mentions()
    {
        return $this->morphMany(Mention::class, 'mentionable');
    }
    
    /**
     * Process and create mentions from message text.
     */
    public function processMentions($text)
    {
        // Extract mentions from text (@username format)
        preg_match_all('/@([a-zA-Z0-9_]+)/', $text, $matches);
        
        if (!empty($matches[1])) {
            $usernames = array_unique($matches[1]);
            
            foreach ($usernames as $username) {
                // Find the mentioned user
                $mentionedUser = User::where('username', $username)->first();
                
                if ($mentionedUser && $mentionedUser->id !== $this->sender_id) {
                    // Create mention record
                    $mention = $this->mentions()->create([
                        'mentioned_user_id' => $mentionedUser->id,
                        'mentioned_by_user_id' => $this->sender_id,
                        'status' => 'unread'
                    ]);
                    
                    // Send notification to the mentioned user
                    $mentionedUser->notify(new MentionNotification($mention));
                }
            }
        }
    }
}
