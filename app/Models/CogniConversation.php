<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CogniConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'question',
        'answer',
        'archived'
    ];

    /**
     * Get the user that owns the conversation.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all messages for a specific conversation
     * 
     * @param string $conversationId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getConversationHistory($conversationId)
    {
        return self::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get all conversations for a user
     * 
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getUserConversations($userId)
    {
        return self::where('user_id', $userId)
            ->select('conversation_id')
            ->distinct()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($item) use ($userId) {
                // Get first and last message for each conversation
                $firstMessage = self::where('conversation_id', $item->conversation_id)
                    ->where('user_id', $userId)
                    ->orderBy('created_at', 'asc')
                    ->first();
                    
                $lastMessage = self::where('conversation_id', $item->conversation_id)
                    ->where('user_id', $userId)
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                // Count messages in conversation
                $messageCount = self::where('conversation_id', $item->conversation_id)
                    ->where('user_id', $userId)
                    ->count();
                
                return [
                    'conversation_id' => $item->conversation_id,
                    'first_message' => $firstMessage ? $firstMessage->question : null,
                    'last_message' => $lastMessage ? $lastMessage->question : null,
                    'message_count' => $messageCount,
                    'updated_at' => $lastMessage ? $lastMessage->created_at : null
                ];
            });
    }
}