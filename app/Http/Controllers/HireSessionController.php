<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\User;
use App\Models\HireSession;
use App\Models\HireRequest;
use App\Models\EducatorRating;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class HireSessionController extends Controller
{
    /**
     * Get all sessions for the authenticated user
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $status = $request->input('status');
        
        $query = HireSession::query()
            ->with(['hireRequest.client', 'hireRequest.tutor'])
            ->whereHas('hireRequest', function ($query) use ($user) {
                $query->where('client_id', $user->id)
                    ->orWhere('tutor_id', $user->id);
            });
            
        if ($status) {
            $query->where('status', $status);
        }
        
        $sessions = $query->orderBy('scheduled_at', 'desc')
            ->paginate(10);
        
        return response()->json([
            'message' => 'Sessions retrieved successfully',
            'sessions' => $sessions
        ]);
    }
    
    /**
     * Get a specific session
     */
    public function show($id)
    {
        $user = Auth::user();
        
        $session = HireSession::with([
                'hireRequest.client', 
                'hireRequest.tutor', 
                'conversation',
                'documents' => function($query) {
                    $query->where('is_active', true)->orderBy('shared_at', 'desc');
                }
            ])
            ->findOrFail($id);
            
        // Check if user is part of this session
        if ($session->hireRequest->client_id !== $user->id && $session->hireRequest->tutor_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        
        // Add document download URLs
        $session->documents->each(function($document) {
            $document->download_url = $document->getDownloadUrl();
        });
        
        return response()->json([
            'message' => 'Session retrieved successfully',
            'session' => $session
        ]);
    }
    
    /**
     * Mark a session as completed
     */
    public function complete($id)
    {
        $user = Auth::user();
        
        $session = HireSession::findOrFail($id);
        
        // Check if user is the educator in this session
        if ($session->hireRequest->tutor_id !== $user->id) {
            return response()->json([
                'message' => 'Only the educator can mark a session as completed'
            ], 403);
        }
        
        // Check if session is in progress
        if ($session->status !== 'in_progress') {
            return response()->json([
                'message' => 'Only in-progress sessions can be marked as completed'
            ], 400);
        }
        
        // Update session
        $session->status = 'completed';
        $session->ended_at = now();
        
        // Calculate duration in minutes if not already set
        if (!$session->duration_minutes && $session->scheduled_at) {
            $session->duration_minutes = $session->scheduled_at->diffInMinutes($session->ended_at);
        }
        
        $session->save();
        
        return response()->json([
            'message' => 'Session marked as completed',
            'session' => $session
        ]);
    }
    
    /**
     * Rate an educator after a completed session
     */
    public function rateEducator(Request $request, $id)
    {
        $user = Auth::user();
        
        // Validate the request
        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Find the session
        $session = HireSession::findOrFail($id);
        
        // Check if user is the client in this session
        if ($session->hireRequest->client_id !== $user->id) {
            return response()->json([
                'message' => 'Only the client can rate the educator'
            ], 403);
        }
        
        // Check if session is completed
        if ($session->status !== 'completed') {
            return response()->json([
                'message' => 'Only completed sessions can be rated'
            ], 400);
        }
        
        // Check if already rated
        if ($session->isRated()) {
            return response()->json([
                'message' => 'You have already rated this session'
            ], 400);
        }
        
        // Create the rating
        $rating = EducatorRating::create([
            'user_id' => $user->id,
            'educator_id' => $session->hireRequest->tutor_id,
            'hire_session_id' => $session->id,
            'rating' => $request->input('rating'),
            'comment' => $request->input('comment')
        ]);
        
        return response()->json([
            'message' => 'Educator rated successfully',
            'rating' => $rating
        ]);
    }
    
    /**
     * Enable or disable messaging for a session
     */
    public function toggleMessaging(Request $request, $id)
    {
        $user = Auth::user();
        
        // Validate request
        $validator = Validator::make($request->all(), [
            'enable_messaging' => 'required|boolean'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Find the session
        $session = HireSession::findOrFail($id);
        
        // Check if user is the educator in this session
        if ($session->hireRequest->tutor_id !== $user->id) {
            return response()->json([
                'message' => 'Only the educator can modify messaging settings'
            ], 403);
        }
        
        // Update messaging permission
        if ($request->input('enable_messaging')) {
            $session->enableMessaging();
            $message = 'Messaging enabled for this session';
        } else {
            $session->disableMessaging();
            $message = 'Messaging disabled for this session';
        }
        
        // If conversation doesn't exist and messaging is enabled, create one
        if ($request->input('enable_messaging') && !$session->conversation_id) {
            $this->createSessionConversation($session);
        }
        
        return response()->json([
            'message' => $message,
            'session' => $session->fresh(['conversation'])
        ]);
    }
    
    /**
     * Create or retrieve conversation for a session
     */
    public function getConversation($id)
    {
        $user = Auth::user();
        
        // Find the session
        $session = HireSession::findOrFail($id);
        
        // Check if user is part of this session
        if (!$session->isParticipant($user)) {
            return response()->json([
                'message' => 'You are not authorized to access this conversation'
            ], 403);
        }
        
        // Check if messaging is enabled
        if (!$session->canMessage()) {
            return response()->json([
                'message' => 'Messaging is not enabled for this session'
            ], 403);
        }
        
        // Create conversation if it doesn't exist
        if (!$session->conversation_id) {
            $conversation = $this->createSessionConversation($session);
        } else {
            $conversation = $session->conversation;
        }
        
        // Load messages and other details
        $conversation->load([
            'userOne', 
            'userTwo', 
            'messages' => function($query) {
                $query->orderBy('created_at', 'desc')->limit(50);
            }, 
            'messages.sender'
        ]);
        
        // Mark messages as read
        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);
        
        return response()->json([
            'message' => 'Conversation retrieved successfully',
            'conversation' => $conversation
        ]);
    }
    
    /**
     * Send a message in the session conversation
     */
    public function sendMessage(Request $request, $id)
    {
        $user = Auth::user();
        
        // Validate request
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:5000',
            'attachment' => 'nullable|file|max:10240' // 10MB max
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Find the session
        $session = HireSession::findOrFail($id);
        
        // Check if user is part of this session
        if (!$session->isParticipant($user)) {
            return response()->json([
                'message' => 'You are not authorized to send messages in this session'
            ], 403);
        }
        
        // Check if messaging is enabled
        if (!$session->canMessage()) {
            return response()->json([
                'message' => 'Messaging is not enabled for this session'
            ], 403);
        }
        
        // Create conversation if it doesn't exist
        if (!$session->conversation_id) {
            $conversation = $this->createSessionConversation($session);
        } else {
            $conversation = $session->conversation;
        }
        
        // Handle attachment if present
        $attachmentData = null;
        $attachmentType = null;
        
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $fileUploadService = app(FileUploadService::class);
            $uploadedFile = $fileUploadService->uploadFile($file, 'hire-session-attachments');
            
            $attachmentData = [
                'path' => $uploadedFile['path'],
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'url' => url("/api/hire-sessions/{$id}/attachments/" . basename($uploadedFile['path']))
            ];
            
            // Determine attachment type based on mime type
            if (strpos($file->getClientMimeType(), 'image/') === 0) {
                $attachmentType = 'image';
            } elseif (strpos($file->getClientMimeType(), 'video/') === 0) {
                $attachmentType = 'video';
            } elseif (strpos($file->getClientMimeType(), 'audio/') === 0) {
                $attachmentType = 'audio';
            } else {
                $attachmentType = 'file';
            }
        }
        
        // Create the message
        $message = $conversation->messages()->create([
            'sender_id' => $user->id,
            'body' => $request->input('message'),
            'attachment' => $attachmentData ? json_encode($attachmentData) : null,
            'attachment_type' => $attachmentType,
            'is_read' => false
        ]);
        
        // Update conversation's last message time
        $conversation->update(['last_message_at' => now()]);
        
        // Process any mentions in the message
        if (method_exists($message, 'processMentions')) {
            $message->processMentions($message->body);
        }
        
        // Load sender relationship
        $message->load('sender');
        
        // Broadcast the message
        broadcast(new MessageSent($message, $user));
        
        return response()->json([
            'message' => 'Message sent successfully',
            'chat_message' => $message
        ]);
    }
    
    /**
     * Create a conversation for a session
     */
    private function createSessionConversation(HireSession $session)
    {
        // Get the client and tutor
        $clientId = $session->hireRequest->client_id;
        $tutorId = $session->hireRequest->tutor_id;
        
        // Create a conversation between the client and tutor
        $conversation = \App\Models\Conversation::create([
            'user_one_id' => $clientId,
            'user_two_id' => $tutorId,
            'hire_session_id' => $session->id,
            'is_restricted' => true,
            'last_message_at' => now()
        ]);
        
        // Update the session with the conversation ID
        $session->update(['conversation_id' => $conversation->id]);
        
        // Add a system message
        $adminUser = \App\Models\User::where('is_admin', true)->first();
        
        if ($adminUser) {
            $conversation->messages()->create([
                'sender_id' => $adminUser->id,
                'body' => "This is a private conversation for your tutoring session. You can share files and messages here. This conversation will remain available even after the session ends.",
                'is_read' => false
            ]);
        }
        
        return $conversation;
    }
    
    /**
     * Download a message attachment
     */
    public function downloadAttachment($id, $filename)
    {
        $user = Auth::user();
        
        // Find the session
        $session = HireSession::findOrFail($id);
        
        // Check if user is part of this session
        if (!$session->isParticipant($user)) {
            return response()->json([
                'message' => 'You are not authorized to access files in this session'
            ], 403);
        }
        
        // Build the full path
        $path = 'hire-session-attachments/' . $filename;
        
        // Check if file exists
        if (!Storage::exists($path)) {
            return response()->json([
                'message' => 'File not found'
            ], 404);
        }
        
        // Get the file mime type
        $mimeType = Storage::mimeType($path);
        
        // Download the file
        return Storage::download($path, $filename, [
            'Content-Type' => $mimeType
        ]);
    }
}
