<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Message;
use App\Models\Conversation;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use App\Events\MessageSent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    public function getConversations()
    {
        $user = Auth::user();

        $conversations = Conversation::where('user_one_id', $user->id)
            ->orWhere('user_two_id', $user->id)
            ->with(['latestMessage'])
            ->get()
            ->map(function ($conversation) use ($user) {
                $otherUser = $conversation->getOtherUser($user);
                $conversation->other_user = $otherUser;
                $conversation->unread_count = $conversation->unreadMessagesFor($user);
                return $conversation;
            });

        return response()->json([
            'conversations' => $conversations
        ]);
    }

    public function getConversation($id)
    {
        $user = Auth::user();
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
        $request->validate([
            'username' => 'required|exists:users,username',
            'message' => 'required_without:attachment|string',
            'attachment' => 'nullable|file|max:10240',
        ]);

        $user = Auth::user();
        $recipient = User::where('username', $request->username)->firstOrFail();

        try {
            DB::beginTransaction();

            $conversation = $user->getConversationWith($recipient);

            $messageData = [
                'sender_id' => $user->id,
                'body' => $request->message ?? '',
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

            $message = $conversation->messages()->create($messageData);

            $conversation->update(['last_message_at' => now()]);

            broadcast(new MessageSent($message))->toOthers();

            DB::commit();

            return response()->json([
                'conversation' => $conversation->load(['messages.sender']),
                'message' => $message->load('sender')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to send message: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to send message: ' . $e->getMessage()
            ], 500);
        }
    }

    public function markAsRead($conversationId)
    {
        $user = Auth::user();
        $conversation = Conversation::findOrFail($conversationId);

        if ($conversation->user_one_id !== $user->id && $conversation->user_two_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Messages marked as read']);
    }

    public function deleteMessage($messageId)
    {
        $user = Auth::user();
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
                'message' => 'Failed to delete message: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getUnreadCount()
    {
        $user = Auth::user();
        $unreadCount = $user->unreadMessagesCount();

        return response()->json(['unread_count' => $unreadCount]);
    }
}