<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\VerificationRequest;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\VerificationStatusNotification;

class VerificationController extends Controller
{
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Submit verification documents
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitVerification(Request $request)
    {
        $user = Auth::user();
        
        // Only educators can submit verification
        if ($user->role !== User::ROLE_EDUCATOR) {
            return response()->json([
                'message' => 'Only educators can submit verification documents'
            ], 403);
        }
        
        // Validate request
        $request->validate([
            'documents' => 'required|array|min:1',
            'documents.*.type' => 'required|string|in:id_card,degree,certificate,teaching_credential,other',
            'documents.*.file' => 'required|file|max:10240', // 10MB max file size
            'documents.*.description' => 'nullable|string|max:255'
        ]);
        
        try {
            DB::beginTransaction();
            
            // Update user verification status
            $user->verification_status = 'in_review';
            $user->save();
            
            $uploadedDocuments = [];
            
            // Process each document
            foreach ($request->documents as $document) {
                $file = $document['file'];
                $documentType = $document['type'];
                $description = $document['description'] ?? null;
                
                // Upload the file
                $documentUrl = $this->fileUploadService->uploadDocument($file, 'verification_documents');
                
                // Create verification request
                $verificationRequest = VerificationRequest::create([
                    'user_id' => $user->id,
                    'document_type' => $documentType,
                    'document_url' => $documentUrl,
                    'status' => 'pending',
                    'notes' => $description
                ]);
                
                $uploadedDocuments[] = [
                    'id' => $verificationRequest->id,
                    'type' => $documentType,
                    'url' => $documentUrl,
                    'description' => $description,
                    'status' => 'pending',
                    'created_at' => $verificationRequest->created_at
                ];
            }
            
            // Update user's verification_documents field
            $user->verification_documents = array_merge($user->verification_documents ?? [], $uploadedDocuments);
            $user->save();
            
            // Notify admins about new verification request
            $admins = User::where('isAdmin', true)->get();
            Notification::send($admins, new VerificationStatusNotification([
                'title' => 'New Verification Request',
                'body' => "Educator {$user->first_name} {$user->last_name} has submitted verification documents",
                'data' => [
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'document_count' => count($uploadedDocuments)
                ],
                'type' => 'new_verification_request'
            ]));
            
            DB::commit();
            
            return response()->json([
                'message' => 'Verification documents submitted successfully',
                'verification_status' => $user->verification_status,
                'documents' => $uploadedDocuments
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error submitting verification: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'An error occurred while submitting verification',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get verification status
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVerificationStatus()
    {
        $user = Auth::user();
        $requests = $user->verificationRequests()
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json([
            'is_verified' => $user->is_verified,
            'verification_status' => $user->verification_status,
            'verification_notes' => $user->verification_notes,
            'verified_at' => $user->verified_at,
            'verification_requests' => $requests
        ]);
    }
    
    /**
     * Admin: Get all verification requests
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllVerificationRequests(Request $request)
    {
        // Check if user is admin
        if (!Auth::user()->isAdmin) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        
        $query = User::where('role', User::ROLE_EDUCATOR)
            ->where('verification_status', '!=', 'pending')
            ->with('verificationRequests');
            
        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('verification_status', $request->status);
        }
        
        $users = $query->orderBy('updated_at', 'desc')
            ->paginate($request->per_page ?? 15);
            
        return response()->json($users);
    }
    
    /**
     * Admin: Get verification details
     *
     * @param string $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVerificationDetails($userId)
    {
        // Check if user is admin
        if (!Auth::user()->isAdmin) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        
        $user = User::with('verificationRequests', 'profile')
            ->findOrFail($userId);
            
        return response()->json([
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'is_verified' => $user->is_verified,
                'verification_status' => $user->verification_status,
                'verification_notes' => $user->verification_notes,
                'verified_at' => $user->verified_at,
                'profile' => $user->profile
            ],
            'verification_requests' => $user->verificationRequests
        ]);
    }
    
    /**
     * Admin: Update verification status
     *
     * @param Request $request
     * @param string $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateVerificationStatus(Request $request, $userId)
    {
        // Check if user is admin
        if (!Auth::user()->isAdmin) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        
        $request->validate([
            'status' => 'required|string|in:approved,rejected',
            'notes' => 'nullable|string|max:500'
        ]);
        
        $user = User::findOrFail($userId);
        
        try {
            DB::beginTransaction();
            
            // Update verification status
            $user->verification_status = $request->status;
            $user->verification_notes = $request->notes;
            
            if ($request->status === 'approved') {
                $user->is_verified = true;
                $user->verified_at = now();
            } else {
                $user->is_verified = false;
                $user->verified_at = null;
            }
            
            $user->save();
            
            // Update all pending verification requests
            $user->verificationRequests()
                ->where('status', 'pending')
                ->update([
                    'status' => $request->status,
                    'notes' => $request->notes,
                    'verified_by' => Auth::id(),
                    'verified_at' => now()
                ]);
                
            // Notify user about verification status
            $user->notify(new VerificationStatusNotification([
                'title' => 'Verification ' . ucfirst($request->status),
                'body' => $request->status === 'approved' 
                    ? 'Your educator account has been verified' 
                    : 'Your verification request has been rejected',
                'data' => [
                    'status' => $request->status,
                    'notes' => $request->notes
                ],
                'type' => 'verification_' . $request->status
            ]));
            
            DB::commit();
            
            return response()->json([
                'message' => 'Verification status updated successfully',
                'user' => [
                    'id' => $user->id,
                    'verification_status' => $user->verification_status,
                    'is_verified' => $user->is_verified,
                    'verified_at' => $user->verified_at
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating verification status: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'An error occurred while updating verification status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Admin: Update document status
     *
     * @param Request $request
     * @param int $requestId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateDocumentStatus(Request $request, $requestId)
    {
        // Check if user is admin
        if (!Auth::user()->isAdmin) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        
        $request->validate([
            'status' => 'required|string|in:approved,rejected',
            'notes' => 'nullable|string|max:500'
        ]);
        
        $verificationRequest = VerificationRequest::findOrFail($requestId);
        
        $verificationRequest->status = $request->status;
        $verificationRequest->notes = $request->notes;
        $verificationRequest->verified_by = Auth::id();
        $verificationRequest->verified_at = now();
        
        if ($verificationRequest->save()) {
            return response()->json([
                'message' => 'Document status updated successfully',
                'verification_request' => $verificationRequest
            ]);
        }
        
        return response()->json([
            'message' => 'Failed to update document status'
        ], 500);
    }
}