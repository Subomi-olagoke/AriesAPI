<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\HireSession;
use App\Models\HireRequest;
use App\Models\EducatorRating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        
        $session = HireSession::with(['hireRequest.client', 'hireRequest.tutor'])
            ->findOrFail($id);
            
        // Check if user is part of this session
        if ($session->hireRequest->client_id !== $user->id && $session->hireRequest->tutor_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        
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
    
}
