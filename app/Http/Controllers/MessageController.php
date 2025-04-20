<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Message;
use App\Models\Conversation;
use App\Models\HiringSession;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use App\Events\MessageSent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    public function getConversations()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        $conversations = Conversation::where('user_one_id', $user->id)
            ->orWhere('user_two_id', $user->id)
            ->with(['latestMessage'])
            ->orderBy('last_message_at', 'desc')
            ->get()
            ->map(function ($conversation) use ($user) {
                $otherUser = $conversation->getOtherUser($user);
                
                // Create a new object with an "id" field set to "0"
                $result = [
                    'id' => '0', // Add this to fix the Swift decoding error
                    'conversation_id' => $conversation->id,
                    'user_one_id' => $conversation->user_one_id,
                    'user_two_id' => $conversation->user_two_id,
                    'last_message_at' => $conversation->last_message_at,
                    'created_at' => $conversation->created_at,
                    'updated_at' => $conversation->updated_at,
                    'other_user' => $otherUser,
                    'unread_count' => $conversation->unreadMessagesFor($user)
                ];
                
                // Include latestMessage if it exists
                if ($conversation->latestMessage) {
                    $result['latest_message'] = $conversation->latestMessage;
                }
                
                return $result;
            });

        return response()->json([
            'conversations' => $conversations
        ]);
    }

    public function getConversation($id)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        $conversation = Conversation::with(['messages.sender'])
            ->findOrFail($id);

        if ($conversation->user_one_id !== $user->id && $conversation->user_two_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $conversation->other_user = $conversation->getOtherUser($user);

        return response()->json([
            'conversation' => $conversation
        ]);
    }

    public function sendMessage(Request $request)
    {
        // Improved validation with better error messages
        $request->validate([
            'username' => 'required|exists:users,username',
            'message' => 'required_without:attachment|string|max:5000',
            'attachment' => 'nullable|file|max:10240',
        ], [
            'username.exists' => 'User not found',
            'message.required_without' => 'Please provide either a message or an attachment',
        ]);

        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'message' => 'User not authenticated'
            ], 401);
        }

        $recipient = User::where('username', $request->username)->first();
        
        if (!$recipient) {
            return response()->json([
                'message' => 'Recipient not found'
            ], 404);
        }

        if ($recipient->id === $user->id) {
            return response()->json([
                'message' => 'You cannot send a message to yourself'
            ], 400);
        }

        // Check if the sender is a learner and the recipient is an educator
        if ($user->role === 'learner' && $recipient->role === 'educator') {
            // Check if they have permission to message this educator
            if (!$user->canMessageEducator($recipient)) {
                return response()->json([
                    'message' => 'You cannot message this educator without an active hiring session'
                ], 403);
            }
        }
        
        // Find existing conversation
        $conversation = Conversation::where(function ($query) use ($user, $recipient) {
            $query->where('user_one_id', $user->id)
                  ->where('user_two_id', $recipient->id);
        })->orWhere(function ($query) use ($user, $recipient) {
            $query->where('user_one_id', $recipient->id)
                  ->where('user_two_id', $user->id);
        })->first();
        
        // If conversation exists, check if it's restricted
        if ($conversation && $conversation->isRestricted()) {
            if (!$conversation->canSendMessages($user)) {
                return response()->json([
                    'message' => 'You do not have permission to send messages in this conversation'
                ], 403);
            }
        }

        try {
            DB::beginTransaction();

            // Sort user IDs to match the pattern used in the Conversation model
            $userIds = [$user->id, $recipient->id];
            sort($userIds);
            $conversationId = implode('_', $userIds);
            
            // Try to find by ID first (more efficient)
            $conversation = Conversation::find($conversationId);
            
            // Fallback to the old query method if not found (for backward compatibility)
            if (!$conversation) {
                $conversation = Conversation::where(function ($query) use ($user, $recipient) {
                    $query->where('user_one_id', $user->id)
                          ->where('user_two_id', $recipient->id);
                })->orWhere(function ($query) use ($user, $recipient) {
                    $query->where('user_one_id', $recipient->id)
                          ->where('user_two_id', $user->id);
                })->first();
            }

            if (!$conversation) {
                $conversation = Conversation::create([
                    'user_one_id' => $user->id,
                    'user_two_id' => $recipient->id,
                    'last_message_at' => now()
                ]);
            }

            $messageData = [
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'body' => $request->message ?? '',
                'is_read' => false
            ];

            if ($request->hasFile('attachment')) {
                $fileUploadService = app(FileUploadService::class);

                $attachmentUrl = $fileUploadService->uploadFile(
                    $request->file('attachment'),
                    'message_attachments'
                );

                $messageData['attachment'] = $attachmentUrl;
                $messageData['attachment_type'] = $request->file('attachment')->getMimeType();
            }

            $message = Message::create($messageData);

            // Process mentions in the message body
            if (!empty($messageData['body'])) {
                $message->processMentions($messageData['body']);
            }

            $conversation->update([
                'last_message_at' => now()
            ]);

            broadcast(new MessageSent($message))->toOthers();

            DB::commit();

            // Load relationships for response
            $message->load('sender');
            
            return response()->json([
                'message' => $message,
                'conversation' => [
                    'id' => $conversation->id,
                    'other_user' => $recipient->only(['id', 'username', 'first_name', 'last_name', 'avatar'])
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to send message: ', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id,
                'recipient_id' => $recipient->id
            ]);
            return response()->json([
                'message' => 'Failed to send message',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    public function markAsRead($conversationId)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        $conversation = Conversation::findOrFail($conversationId);

        if ($conversation->user_one_id !== $user->id && $conversation->user_two_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $updatedCount = $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'message' => 'Messages marked as read',
            'updated_count' => $updatedCount
        ]);
    }

    public function deleteMessage($messageId)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        $message = Message::findOrFail($messageId);

        if ($message->sender_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            DB::beginTransaction();

            if ($message->attachment) {
                $fileUploadService = app(FileUploadService::class);
                $fileUploadService->deleteFile($message->attachment);
            }

            $message->delete();

            DB::commit();

            return response()->json(['message' => 'Message deleted']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete message: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete message',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    public function getUnreadCount()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        $unreadCount = $user->unreadMessagesCount();

        return response()->json(['unread_count' => $unreadCount]);
    }
}