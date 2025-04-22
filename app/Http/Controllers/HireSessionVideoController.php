<?php

namespace App\Http\Controllers;

use App\Events\HireSessionEnded;
use App\Events\HireSessionSignaling;
use App\Events\HireSessionStarted;
use App\Events\HireSessionUserJoined;
use App\Models\HireSession;
use App\Models\HireSessionParticipant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class HireSessionVideoController extends Controller
{
    /**
     * Start a video session.
     * Only the tutor can start a session.
     */
    public function startSession(Request $request, $id)
    {
        $user = Auth::user();
        
        // Get the session
        $session = HireSession::findOrFail($id);
        
        // Check if user is the tutor
        if ($session->hireRequest->tutor_id !== $user->id) {
            return response()->json([
                'message' => 'Only the educator can start a video session'
            ], 403);
        }
        
        // Check if the session is already in progress
        if ($session->isVideoSessionActive()) {
            return response()->json([
                'message' => 'Video session is already active',
                'session' => $session
            ]);
        }
        
        // Validate request
        $validated = $request->validate([
            'settings' => 'sometimes|nullable|array',
            'settings.enable_chat' => 'sometimes|boolean',
            'settings.mute_on_join' => 'sometimes|boolean',
            'settings.video_on_join' => 'sometimes|boolean',
            'settings.allow_screen_sharing' => 'sometimes|boolean',
            'settings.recording_enabled' => 'sometimes|boolean'
        ]);
        
        try {
            DB::beginTransaction();
            
            // Start the video session
            $session->startVideoSession($request->settings ?? null);
            
            // Add the tutor as the first participant (moderator)
            $participant = $session->participants()->create([
                'user_id' => $user->id,
                'role' => 'moderator',
                'joined_at' => now(),
                'preferences' => [
                    'video' => $session->video_session_settings['video_on_join'] ?? true,
                    'audio' => !($session->video_session_settings['mute_on_join'] ?? false),
                    'screen_share' => false
                ]
            ]);
            
            DB::commit();
            
            // Broadcast video session started event
            broadcast(new HireSessionStarted($session, $user));
            
            // Also broadcast that the tutor has joined
            broadcast(new HireSessionUserJoined($session, $user, $participant));
            
            return response()->json([
                'message' => 'Video session started successfully',
                'session' => $session->fresh(),
                'meeting_id' => $session->meeting_id,
                'participant' => $participant
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to start video session: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to start video session: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Join a video session.
     */
    public function joinSession($id)
    {
        $user = Auth::user();
        
        // Get the session
        $session = HireSession::findOrFail($id);
        
        // Check if user is part of this session
        if (!$session->isParticipant($user)) {
            return response()->json([
                'message' => 'You are not authorized to join this session'
            ], 403);
        }
        
        // Check if the session is active
        if (!$session->isVideoSessionActive()) {
            return response()->json([
                'message' => 'This video session is not currently active',
                'status' => $session->video_session_status
            ], 400);
        }
        
        try {
            // Check if already a participant
            $existingParticipant = $session->participants()
                ->where('user_id', $user->id)
                ->whereNull('left_at')
                ->first();
                
            if ($existingParticipant) {
                return response()->json([
                    'message' => 'You are already a participant in this session',
                    'participant' => $existingParticipant,
                    'session' => $session->load('activeParticipants.user')
                ]);
            }
            
            // Determine role (learner joins as participant)
            $role = $session->hireRequest->tutor_id === $user->id ? 'moderator' : 'participant';
            
            // Create a new participant
            $participant = $session->participants()->create([
                'user_id' => $user->id,
                'role' => $role,
                'joined_at' => now(),
                'preferences' => [
                    'video' => $session->video_session_settings['video_on_join'] ?? true,
                    'audio' => !($session->video_session_settings['mute_on_join'] ?? false),
                    'screen_share' => false
                ]
            ]);
            
            // Broadcast user joined event
            broadcast(new HireSessionUserJoined($session, $user, $participant));
            
            // Load active participants with user data
            $session->load(['activeParticipants.user']);
            
            return response()->json([
                'message' => 'Joined session successfully',
                'session' => $session,
                'participant' => $participant
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to join video session: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to join video session: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Leave a video session.
     */
    public function leaveSession($id)
    {
        $user = Auth::user();
        
        // Get the session
        $session = HireSession::findOrFail($id);
        
        try {
            // Get the participant
            $participant = $session->participants()
                ->where('user_id', $user->id)
                ->whereNull('left_at')
                ->first();
                
            if (!$participant) {
                return response()->json([
                    'message' => 'You are not an active participant in this session'
                ], 400);
            }
            
            // Mark as left
            $participant->update(['left_at' => now()]);
            
            return response()->json([
                'message' => 'Left the session successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to leave video session: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to leave video session: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * End a video session.
     * Only the moderator (tutor) can end the session.
     */
    public function endSession($id)
    {
        $user = Auth::user();
        
        // Get the session
        $session = HireSession::findOrFail($id);
        
        // Check if the session is active
        if (!$session->isVideoSessionActive()) {
            return response()->json([
                'message' => 'This video session is not currently active'
            ], 400);
        }
        
        // Check if user is the tutor or has moderator role
        $participant = $session->participants()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->first();
            
        if (!$participant || $participant->role !== 'moderator') {
            return response()->json([
                'message' => 'Only the moderator can end this session'
            ], 403);
        }
        
        try {
            // End the session
            $session->endVideoSession();
            
            // Broadcast session ended event
            broadcast(new HireSessionEnded($session, $user));
            
            return response()->json([
                'message' => 'Video session ended successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to end video session: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to end video session: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get the current status of a video session.
     */
    public function getSessionStatus($id)
    {
        $user = Auth::user();
        
        // Get the session
        $session = HireSession::findOrFail($id);
        
        // Check if user is part of this session
        if (!$session->isParticipant($user)) {
            return response()->json([
                'message' => 'You are not authorized to view this session'
            ], 403);
        }
        
        // Get active participants
        $activeParticipants = $session->getActiveParticipantsWithUsers();
        
        // Check if user is an active participant
        $isActiveParticipant = $activeParticipants->contains('user_id', $user->id);
        
        return response()->json([
            'status' => $session->video_session_status,
            'meeting_id' => $session->meeting_id,
            'started_at' => $session->video_session_started_at,
            'ended_at' => $session->video_session_ended_at,
            'settings' => $session->video_session_settings,
            'active_participants' => $activeParticipants,
            'is_active_participant' => $isActiveParticipant,
            'recording_url' => $session->recording_url
        ]);
    }
    
    /**
     * Update participant preferences (audio/video/screen share).
     */
    public function updatePreferences(Request $request, $id)
    {
        $user = Auth::user();
        
        // Get the session
        $session = HireSession::findOrFail($id);
        
        // Validate request
        $validated = $request->validate([
            'preferences' => 'required|array',
            'preferences.video' => 'boolean',
            'preferences.audio' => 'boolean',
            'preferences.screen_share' => 'boolean'
        ]);
        
        try {
            // Get participant
            $participant = $session->participants()
                ->where('user_id', $user->id)
                ->whereNull('left_at')
                ->first();
                
            if (!$participant) {
                return response()->json([
                    'message' => 'You are not an active participant in this session'
                ], 400);
            }
            
            // Update preferences
            $participant->update([
                'preferences' => $validated['preferences']
            ]);
            
            // Broadcast to other participants (could create a dedicated event if needed)
            
            return response()->json([
                'message' => 'Preferences updated successfully',
                'preferences' => $participant->preferences
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update preferences: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to update preferences: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Handle RTC signaling between peers.
     */
    public function signal(Request $request, $id)
    {
        $user = Auth::user();
        
        // Get the session
        $session = HireSession::findOrFail($id);
        
        // Check if user is an active participant
        $isParticipant = $session->participants()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->exists();
            
        if (!$isParticipant) {
            return response()->json([
                'message' => 'You must be an active participant to send signals'
            ], 403);
        }
        
        // Validate request
        $validated = $request->validate([
            'to_user_id' => 'required|string|exists:users,id',
            'signal_data' => 'required'
        ]);
        
        // Check if the recipient is an active participant
        $recipientIsParticipant = $session->participants()
            ->where('user_id', $validated['to_user_id'])
            ->whereNull('left_at')
            ->exists();
            
        if (!$recipientIsParticipant) {
            return response()->json([
                'message' => 'The recipient is not an active participant'
            ], 400);
        }
        
        // Broadcast the signal to the recipient
        broadcast(new HireSessionSignaling(
            $session->id,
            $user->id,
            $validated['to_user_id'],
            $validated['signal_data']
        ));
        
        return response()->json(['status' => 'success']);
    }
    
    /**
     * Send ICE candidate for WebRTC connection.
     */
    public function sendIceCandidate(Request $request, $id)
    {
        $user = Auth::user();
        
        // Get the session
        $session = HireSession::findOrFail($id);
        
        // Check if user is an active participant
        $isParticipant = $session->participants()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->exists();
            
        if (!$isParticipant) {
            return response()->json([
                'message' => 'You must be an active participant to send signals'
            ], 403);
        }
        
        // Validate request
        $validated = $request->validate([
            'to_user_id' => 'required|string|exists:users,id',
            'candidate' => 'required'
        ]);
        
        // Check if the recipient is an active participant
        $recipientIsParticipant = $session->participants()
            ->where('user_id', $validated['to_user_id'])
            ->whereNull('left_at')
            ->exists();
            
        if (!$recipientIsParticipant) {
            return response()->json([
                'message' => 'The recipient is not an active participant'
            ], 400);
        }
        
        // Broadcast the ICE candidate to the recipient
        broadcast(new HireSessionSignaling(
            $session->id,
            $user->id,
            $validated['to_user_id'],
            $validated['candidate'],
            'ice'
        ));
        
        return response()->json(['status' => 'success']);
    }
    
    /**
     * Report connection quality.
     */
    public function reportConnectionQuality(Request $request, $id)
    {
        $user = Auth::user();
        
        // Get the session
        $session = HireSession::findOrFail($id);
        
        // Validate request
        $validated = $request->validate([
            'quality' => 'required|string|in:excellent,good,fair,poor,very-poor'
        ]);
        
        try {
            // Update the participant's connection quality
            $participant = $session->participants()
                ->where('user_id', $user->id)
                ->whereNull('left_at')
                ->first();
                
            if (!$participant) {
                return response()->json([
                    'message' => 'You are not an active participant in this session'
                ], 400);
            }
            
            $participant->update([
                'connection_quality' => $validated['quality']
            ]);
            
            return response()->json([
                'message' => 'Connection quality reported successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to report connection quality: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to report connection quality: ' . $e->getMessage()
            ], 500);
        }
    }
}
