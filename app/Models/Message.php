<?php
namespace App\Models;

use App\Notifications\MentionNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    // Disable auto-incrementing ID
    public $incrementing = false;
    
    // Set ID type to string
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'conversation_id',
        'sender_id',
        'body',
        'attachment',
        'attachment_type',
        'is_read',
    ];

    protected $casts = [
        'id' => 'string',
        'is_read' => 'boolean',
    ];

    // Automatically generate UUID when creating a new message
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
     * Get the conversation that owns the message.
     */
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the sender of the message.
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Mark the message as read.
     */
    public function markAsRead()
    {
        if (!$this->is_read) {
            $this->update(['is_read' => true]);
        }
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
     *
     * @param string $text The message text
     * @return void
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