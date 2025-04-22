<?php

namespace App\Http\Controllers;

use App\Models\HireSession;
use App\Models\HireSessionDocument;
use App\Events\DocumentShared;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class HireSessionDocumentController extends Controller
{
    protected $fileUploadService;
    
    /**
     * Create a new controller instance.
     */
    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }
    
    /**
     * Get all documents for a hire session.
     */
    public function index($sessionId)
    {
        $user = Auth::user();
        
        // Find the session
        $session = HireSession::findOrFail($sessionId);
        
        // Check if user is part of this session
        if (!$session->isParticipant($user)) {
            return response()->json([
                'message' => 'You are not authorized to view documents for this session'
            ], 403);
        }
        
        // Get all documents for this session
        $documents = $session->documents()
            ->with('user')
            ->where('is_active', true)
            ->get()
            ->map(function($document) {
                $document->download_url = $document->getDownloadUrl();
                return $document;
            });
        
        return response()->json([
            'message' => 'Documents retrieved successfully',
            'documents' => $documents
        ]);
    }
    
    /**
     * Upload a document to a hire session.
     */
    public function store(Request $request, $sessionId)
    {
        $user = Auth::user();
        
        // Find the session
        $session = HireSession::findOrFail($sessionId);
        
        // Check if user is part of this session
        if (!$session->isParticipant($user)) {
            return response()->json([
                'message' => 'You are not authorized to share documents in this session'
            ], 403);
        }
        
        // Validate request
        $validator = Validator::make($request->all(), [
            'document' => 'required|file|max:20480', // 20MB max file size
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Upload file to storage
        $file = $request->file('document');
        $uploadedFile = $this->fileUploadService->uploadFile($file, 'hire-session-documents');
        
        // Create document record
        $document = HireSessionDocument::create([
            'hire_session_id' => $session->id,
            'user_id' => $user->id,
            'title' => $request->input('title'),
            'file_path' => $uploadedFile['path'],
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'description' => $request->input('description'),
            'is_active' => true,
            'shared_at' => now()
        ]);
        
        // Load relationship
        $document->load('user');
        $document->download_url = $document->getDownloadUrl();
        
        // Broadcast document shared event
        broadcast(new DocumentShared($document, $session));
        
        // Also create a message about the shared document
        $conversation = $session->conversation;
        if ($conversation) {
            $message = $conversation->messages()->create([
                'sender_id' => $user->id,
                'body' => "Shared a document: {$document->title}",
                'attachment' => json_encode([
                    'type' => 'document',
                    'document_id' => $document->id,
                    'title' => $document->title,
                    'file_type' => $document->file_type
                ]),
                'attachment_type' => 'document'
            ]);
            
            // Update conversation's last message time
            $conversation->update(['last_message_at' => now()]);
            
            // Process any mentions in the message
            if (method_exists($message, 'processMentions')) {
                $message->processMentions($message->body);
            }
        }
        
        return response()->json([
            'message' => 'Document uploaded successfully',
            'document' => $document
        ], 201);
    }
    
    /**
     * Get a specific document.
     */
    public function show($sessionId, $documentId)
    {
        $user = Auth::user();
        
        // Find the document
        $document = HireSessionDocument::with('user')
            ->where('hire_session_id', $sessionId)
            ->where('is_active', true)
            ->findOrFail($documentId);
        
        // Check if user is allowed to view this document
        if (!$document->isViewableBy($user)) {
            return response()->json([
                'message' => 'You are not authorized to view this document'
            ], 403);
        }
        
        // Add download URL
        $document->download_url = $document->getDownloadUrl();
        
        return response()->json([
            'message' => 'Document retrieved successfully',
            'document' => $document
        ]);
    }
    
    /**
     * Download a document.
     */
    public function download($sessionId, $documentId)
    {
        $user = Auth::user();
        
        // Find the document
        $document = HireSessionDocument::where('hire_session_id', $sessionId)
            ->where('is_active', true)
            ->findOrFail($documentId);
        
        // Check if user is allowed to view this document
        if (!$document->isViewableBy($user)) {
            return response()->json([
                'message' => 'You are not authorized to download this document'
            ], 403);
        }
        
        // Get the file from storage
        if (!Storage::exists($document->file_path)) {
            return response()->json([
                'message' => 'Document file not found'
            ], 404);
        }
        
        // Generate a sanitized filename for download
        $extension = pathinfo($document->file_path, PATHINFO_EXTENSION);
        $downloadName = str_slug($document->title) . '.' . $extension;
        
        return Storage::download($document->file_path, $downloadName, [
            'Content-Type' => $document->file_type
        ]);
    }
    
    /**
     * Delete a document (soft delete).
     */
    public function destroy($sessionId, $documentId)
    {
        $user = Auth::user();
        
        // Find the document
        $document = HireSessionDocument::where('hire_session_id', $sessionId)
            ->where('is_active', true)
            ->findOrFail($documentId);
        
        // Check if user is the uploader or has permission
        if ($document->user_id !== $user->id) {
            // Allow educator to delete any document if they are part of the session
            $session = $document->hireSession;
            if ($session->hireRequest->tutor_id !== $user->id) {
                return response()->json([
                    'message' => 'You are not authorized to delete this document'
                ], 403);
            }
        }
        
        // Soft delete by marking as inactive
        $document->update(['is_active' => false]);
        
        return response()->json([
            'message' => 'Document deleted successfully'
        ]);
    }
}
