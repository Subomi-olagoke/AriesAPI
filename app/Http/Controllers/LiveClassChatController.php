<?php

namespace App\Http\Controllers;

use App\Models\LiveClass;
use App\Models\LiveClassChat;
use App\Models\User;
use App\Events\LiveClassChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LiveClassChatController extends Controller
{
    /**
     * Send a message in a live class chat.
     * User must be a participant in the class.
     */
    public function sendMessage(Request $request, $classId)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
        ]);
        
        $liveClass = LiveClass::findOrFail($classId);
        $user = auth()->user();
        
        // Check if user is a participant
        $isParticipant = $liveClass->participants()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->exists();
            
        if (!$isParticipant) {
            return response()->json([
                'message' => 'You must be a participant to send messages in this class',
                'join_required' => true
            ], 403);
        }
        
        // Check if chat is enabled in class settings
        if (!($liveClass->settings['enable_chat'] ?? false)) {
            return response()->json([
                'message' => 'Chat is disabled for this class'
            ], 403);
        }
        
        try {
            $chatMessage = LiveClassChat::create([
                'live_class_id' => $liveClass->id,
                'user_id' => $user->id,
                'message' => $validated['message'],
                'type' => 'text',
            ]);
            
            $chatMessage->load('user:id,username,first_name,last_name,avatar,role');
            
            // Broadcast to other participants
            broadcast(new LiveClassChatMessage($chatMessage))->toOthers();
            
            return response()->json([
                'message' => 'Message sent successfully',
                'chat_message' => $chatMessage
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send live class chat message: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to send message: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get chat history for a live class.
     * User must be a participant in the class.
     */
    public function getChatHistory($classId)
    {
        $liveClass = LiveClass::findOrFail($classId);
        $user = auth()->user();
        
        // Check if user is a participant
        $isParticipant = $liveClass->participants()
            ->where('user_id', $user->id)
            ->exists();
            
        if (!$isParticipant) {
            return response()->json([
                'message' => 'You must be a participant to view chat history',
                'join_required' => true
            ], 403);
        }
        
        // Get chat messages with user info
        $chatMessages = $liveClass->chatMessages()
            ->with('user:id,username,first_name,last_name,avatar,role')
            ->orderBy('created_at')
            ->get();
            
        return response()->json([
            'chat_messages' => $chatMessages,
            'chat_enabled' => $liveClass->settings['enable_chat'] ?? false
        ]);
    }
    
    /**
     * Delete a chat message.
     * Only the message author or a moderator can delete a message.
     */
    public function deleteMessage($messageId)
    {
        $chatMessage = LiveClassChat::findOrFail($messageId);
        $liveClass = LiveClass::findOrFail($chatMessage->live_class_id);
        $user = auth()->user();
        
        // Check if user is the message author or a moderator
        $participant = $liveClass->participants()
            ->where('user_id', $user->id)
            ->first();
            
        if (!$participant) {
            return response()->json([
                'message' => 'You are not a participant in this class'
            ], 403);
        }
        
        $isAuthor = $chatMessage->user_id === $user->id;
        $isModerator = $participant->role === 'moderator';
        
        if (!$isAuthor && !$isModerator) {
            return response()->json([
                'message' => 'You can only delete your own messages'
            ], 403);
        }
        
        try {
            $chatMessage->delete();
            
            return response()->json([
                'message' => 'Message deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to delete live class chat message: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to delete message: ' . $e->getMessage()
            ], 500);
        }
    }
}