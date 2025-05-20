<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CogniChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_id',
        'sender_type',
        'content_type',
        'content',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the chat that owns the message.
     */
    public function chat()
    {
        return $this->belongsTo(CogniChat::class, 'chat_id');
    }
    
    /**
     * Scope a query to only include user messages.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUser($query)
    {
        return $query->where('sender_type', 'user');
    }
    
    /**
     * Scope a query to only include cogni messages.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCogni($query)
    {
        return $query->where('sender_type', 'cogni');
    }
    
    /**
     * Scope a query to filter by content type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('content_type', $type);
    }
    
    /**
     * Get messages by type for a specific chat
     * 
     * @param int $chatId
     * @param string $contentType
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getMessagesOfType($chatId, $contentType)
    {
        return static::where('chat_id', $chatId)
            ->where('content_type', $contentType)
            ->orderBy('created_at', 'asc')
            ->get();
    }
    
    /**
     * Format the message content for display based on content type
     * 
     * @return array
     */
    public function formattedContent()
    {
        $result = [
            'id' => $this->id,
            'sender_type' => $this->sender_type,
            'content_type' => $this->content_type,
            'created_at' => $this->created_at,
            'metadata' => $this->metadata ?? [],
        ];
        
        switch ($this->content_type) {
            case 'text':
                $result['content'] = $this->content;
                break;
                
            case 'link':
                $result['content'] = $this->content;
                $result['preview'] = $this->metadata['preview'] ?? null;
                break;
                
            case 'image':
                $result['content'] = $this->content; // URL to the image
                $result['thumbnail'] = $this->metadata['thumbnail'] ?? null;
                break;
                
            case 'document':
                $result['content'] = $this->content; // URL to the document
                $result['filename'] = $this->metadata['filename'] ?? 'document';
                $result['file_type'] = $this->metadata['file_type'] ?? 'unknown';
                break;
        }
        
        return $result;
    }
}