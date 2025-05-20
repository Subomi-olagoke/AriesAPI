<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CogniChat extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'share_key',
        'is_public',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_public' => 'boolean',
    ];
    
    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            // Generate a unique share key if not provided
            if (empty($model->share_key)) {
                $model->share_key = static::generateUniqueShareKey();
            }
            
            // Generate a default title if not provided
            if (empty($model->title)) {
                $model->title = 'Chat ' . now()->format('M d, Y');
            }
        });
    }
    
    /**
     * Generate a unique share key for the chat
     *
     * @return string
     */
    public static function generateUniqueShareKey()
    {
        $shareKey = 'chat_' . Str::random(10);
        
        // Ensure it's unique
        while (static::where('share_key', $shareKey)->exists()) {
            $shareKey = 'chat_' . Str::random(10);
        }
        
        return $shareKey;
    }

    /**
     * Get the user that owns the chat.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the messages for this chat.
     */
    public function messages()
    {
        return $this->hasMany(CogniChatMessage::class, 'chat_id');
    }
    
    /**
     * Find a chat by its share key
     * 
     * @param string $shareKey
     * @return CogniChat|null
     */
    public static function findByShareKey($shareKey)
    {
        return static::where('share_key', $shareKey)->first();
    }
    
    /**
     * Get all chats for a specific user
     * 
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getChatsForUser($userId)
    {
        return static::where('user_id', $userId)
            ->orderBy('updated_at', 'desc')
            ->get();
    }
    
    /**
     * Create a new chat from shared content
     * 
     * @param int $userId
     * @param string $contentType
     * @param string $content
     * @param array $metadata
     * @return CogniChat
     */
    public static function createFromSharedContent($userId, $contentType, $content, $metadata = [])
    {
        // Create the chat
        $chat = static::create([
            'user_id' => $userId,
            'title' => static::generateTitleFromContent($contentType, $content, $metadata),
            'is_public' => false,
        ]);
        
        // Add the first message from the shared content
        $chat->messages()->create([
            'sender_type' => 'user',
            'content_type' => $contentType,
            'content' => $content,
            'metadata' => $metadata,
        ]);
        
        return $chat;
    }
    
    /**
     * Generate a title based on the shared content
     * 
     * @param string $contentType
     * @param string $content
     * @param array $metadata
     * @return string
     */
    protected static function generateTitleFromContent($contentType, $content, $metadata = [])
    {
        switch ($contentType) {
            case 'link':
                return 'Chat about link: ' . parse_url($content, PHP_URL_HOST);
                
            case 'image':
                return 'Chat about image';
                
            case 'document':
                $filename = $metadata['filename'] ?? 'document';
                return 'Chat about document: ' . $filename;
                
            case 'text':
            default:
                // Use first few words of text for the title
                $words = str_word_count($content, 1);
                $titleWords = array_slice($words, 0, 5);
                $titleText = implode(' ', $titleWords);
                
                // If title is too short, use a generic title
                if (strlen($titleText) < 10) {
                    return 'New chat ' . now()->format('M d, Y');
                }
                
                return 'Chat about: ' . $titleText . '...';
        }
    }
}