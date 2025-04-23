<?php

namespace App\Http\Controllers;

use App\Models\LiveClass;
use App\Models\LiveClassChat;
use App\Events\LiveClassChatMessage;
use App\Services\ContentModerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LiveClassChatController extends Controller
{
    /**
     * Send a chat message to a live class
     *
     * @param Request $request
     * @param int $classId
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendMessage(Request $request, $classId)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'type' => 'nullable|string|in:text,system,file',
            'metadata' => 'nullable|array',
        ]);

        // Find the live class
        $liveClass = LiveClass::findOrFail($classId);
        
        // Check if the user is a participant in this class
        $participant = $liveClass->participants()
            ->where('user_id', auth()->id())
            ->whereNull('left_at')
            ->first();
            
        if (!$participant) {
            return response()->json([
                'message' => 'You are not a participant in this class'
            ], 403);
        }
        
        // Moderate content before processing
        $contentModerationService = app(ContentModerationService::class);
        $moderationResult = $contentModerationService->analyzeText($request->message);
        
        if (!$moderationResult['isAllowed']) {
            return response()->json([
                'message' => $moderationResult['reason'] ?? 'Your message contains inappropriate content that is not allowed'
            ], 422);
        }
        
        try {
            // Create the chat message
            $chatMessage = LiveClassChat::create([
                'live_class_id' => $liveClass->id,
                'user_id' => auth()->id(),
                'message' => $request->message,
                'type' => $request->type ?? 'text',
                'metadata' => $request->metadata ?? null,
            ]);
            
            // Broadcast the message to all participants
            broadcast(new LiveClassChatMessage($chatMessage->load('user')))->toOthers();
            
            return response()->json([
                'message' => 'Chat message sent successfully',
                'chat_message' => $chatMessage
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error sending chat message: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to send chat message'
            ], 500);
        }
    }
    
    /**
     * Get chat history for a live class
     *
     * @param Request $request
     * @param int $classId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getChatHistory(Request $request, $classId)
    {
        // Find the live class
        $liveClass = LiveClass::findOrFail($classId);
        
        // Check if the user is a participant in this class
        $participant = $liveClass->participants()
            ->where('user_id', auth()->id())
            ->first();
            
        if (!$participant) {
            return response()->json([
                'message' => 'You are not a participant in this class'
            ], 403);
        }
        
        // Get the chat history with pagination
        $limit = $request->get('limit', 50);
        $chatMessages = LiveClassChat::where('live_class_id', $liveClass->id)
            ->with('user:id,username,first_name,last_name,avatar,role')
            ->orderBy('created_at', 'desc')
            ->paginate($limit);
            
        return response()->json([
            'chat_messages' => $chatMessages
        ]);
    }
    
    /**
     * Delete a chat message (only for moderators or message owner)
     *
     * @param Request $request
     * @param int $messageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteMessage(Request $request, $messageId)
    {
        $chatMessage = LiveClassChat::findOrFail($messageId);
        
        // Check if the user is a moderator or the message owner
        $liveClass = $chatMessage->liveClass;
        $participant = $liveClass->participants()
            ->where('user_id', auth()->id())
            ->first();
            
        if (!$participant || ($participant->role !== 'moderator' && $chatMessage->user_id !== auth()->id())) {
            return response()->json([
                'message' => 'You do not have permission to delete this message'
            ], 403);
        }
        
        try {
            $chatMessage->delete();
            
            return response()->json([
                'message' => 'Chat message deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error deleting chat message: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to delete chat message'
            ], 500);
        }
    }
}