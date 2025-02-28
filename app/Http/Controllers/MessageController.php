<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Message;
use App\Models\Conversation;
use Illuminate\Http\Request;
use App\Events\MessageSent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    /**
     * Get all conversations for the authenticated user.
     */
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

    /**
     * Get a specific conversation.
     */
    public function getConversation($id)
    {
        $user = Auth::user();
        $conversation = Conversation::with(['messages.sender'])
            ->findOrFail($id);

        // Check if user is a participant
        if ($conversation->user_one_id !== $user->id && $conversation->user_two_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Mark unread messages as read
        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);
            
        // Add other user details
        $conversation->other_user = $conversation->getOtherUser($user);

        return response()->json([
            'conversation' => $conversation
        ]);
    }

    /**
     * Start a conversation or send a message to an existing one.
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'recipient_id' => 'required|exists:users,id',
            'message' => 'required_without:attachment|string',
            'attachment' => 'nullable|file|max:10240', // 10MB max
        ]);

        $user = Auth::user();
        $recipient = User::findOrFail($request->recipient_id);
        
        // Get or create conversation
        $conversation = $user->getConversationWith($recipient);
        
        $messageData = [
            'sender_id' => $user->id,
            'body' => $request->message ?? '',
        ];

        // Handle file attachment
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('message_attachments', 'public');
            $messageData['attachment'] = $path;
            $messageData['attachment_type'] = $file->getMimeType();
        }

        // Create message
        $message = $conversation->messages()->create($messageData);
        
        // Update conversation's last_message_at
        $conversation->update(['last_message_at' => now()]);
        
        // Broadcast the message event
        broadcast(new MessageSent($message))->toOthers();
        
        return response()->json([
            'conversation' => $conversation->load(['messages.sender']),
            'message' => $message->load('sender')
        ], 201);
    }

    /**
     * Mark all messages in a conversation as read.
     */
    public function markAsRead($conversationId)
    {
        $user = Auth::user();
        $conversation = Conversation::findOrFail($conversationId);
        
        // Check if user is a participant
        if ($conversation->user_one_id !== $user->id && $conversation->user_two_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        // Mark messages as read
        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);
        
        return response()->json(['message' => 'Messages marked as read']);
    }

    /**
     * Delete a message.
     */
    public function deleteMessage($messageId)
    {
        $user = Auth::user();
        $message = Message::findOrFail($messageId);
        
        // Check if user is the sender
        if ($message->sender_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        // Soft delete the message
        $message->delete();
        
        return response()->json(['message' => 'Message deleted']);
    }

    /**
     * Get the count of unread messages.
     */
    public function getUnreadCount()
    {
        $user = Auth::user();
        $unreadCount = $user->unreadMessagesCount();
        
        return response()->json(['unread_count' => $unreadCount]);
    }
}