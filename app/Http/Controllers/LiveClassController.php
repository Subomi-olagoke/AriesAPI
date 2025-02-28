<?php

namespace App\Http\Controllers;

use App\Models\LiveClass;
use App\Models\Subscription;
use Illuminate\Http\Request;
use App\Events\UserJoinedClass;
use App\Events\RTCSignaling;
use App\Events\IceCandidateSignal;
use App\Events\ParticipantSettingsUpdated;
use App\Events\ClassEnded;
use App\Events\StreamStarted;
use App\Events\StreamEnded;
use App\Events\ConnectionQualityWarning;

class LiveClassController extends Controller
{
    /**
     * Check if the user has an active subscription.
     */
    private function checkSubscription()
    {
        $subscription = Subscription::where('user_id', auth()->id())
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();
            
        return $subscription !== null;
    }

    /**
     * List all live classes regardless of status.
     * No subscription is required to view the list.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Retrieve all live classes (including those that have ended)
        $liveClasses = LiveClass::with('teacher')
            ->orderBy('scheduled_at', 'asc')
            ->get();

        return response()->json([
            'message'      => 'Live classes retrieved successfully',
            'live_classes' => $liveClasses
        ]);
    }

    /**
     * Show details for a specific live class.
     * No subscription is required to view class details.
     *
     * @param LiveClass $liveClass
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(LiveClass $liveClass)
    {
        $liveClass->load(['teacher', 'activeParticipants.user']);
        return response()->json($liveClass);
    }

    /**
     * Create a new live class.
     * Subscription is required.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if (!$this->checkSubscription()) {
            return response()->json(['message' => 'Active subscription required to create live classes. Please subscribe to continue.'], 403);
        }

        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'scheduled_at' => 'required|date|after:now',
        ]);

        $meetingId = LiveClass::generateMeetingId();

        $liveClass = LiveClass::create([
            'title'        => $validated['title'],
            'description'  => $validated['description'],
            'scheduled_at' => $validated['scheduled_at'],
            'teacher_id'   => auth()->id(),
            'meeting_id'   => $meetingId,
            'settings'     => [
                'enable_chat'   => true,
                'mute_on_join'  => true,
                'video_on_join' => true,
            ],
            'status'       => 'scheduled'
        ]);

        $liveClass->load('teacher');

        return response()->json([
            'message'    => 'Live class created successfully',
            'live_class' => $liveClass,
            'meeting_id' => $meetingId
        ], 201);
    }

    /**
     * Join a live class.
     * Subscription is required.
     *
     * @param LiveClass $liveClass
     * @return \Illuminate\Http\JsonResponse
     */
    public function join(LiveClass $liveClass)
    {
        if (!$this->checkSubscription()) {
            return response()->json([
                'message' => 'Active subscription required to access live classes. Please subscribe to continue.',
                'subscription_required' => true
            ], 403);
        }

        $participant = $liveClass->participants()->create([
            'user_id'     => auth()->id(),
            'role'        => auth()->id() === $liveClass->teacher_id ? 'moderator' : 'participant',
            'joined_at'   => now(),
            'preferences' => [
                'video'        => true,
                'audio'        => true,
                'screen_share' => false
            ]
        ]);

        if ($liveClass->status === 'scheduled') {
            $liveClass->update(['status' => 'live']);
        }

        broadcast(new UserJoinedClass($liveClass, auth()->user(), $participant))->toOthers();

        return response()->json([
            'message'     => 'Joined successfully',
            'participant' => $participant,
            'class'       => $liveClass->load('teacher', 'activeParticipants.user')
        ]);
    }

    /**
     * End a live class.
     * Only moderators (typically the teacher) can end the class.
     *
     * @param LiveClass $liveClass
     * @return \Illuminate\Http\JsonResponse
     */
    public function end(LiveClass $liveClass)
    {
        $participant = $liveClass->participants()
            ->where('user_id', auth()->id())
            ->first();

        if (!$participant || $participant->role !== 'moderator') {
            return response()->json(['message' => 'Only moderators can end the class'], 403);
        }

        $liveClass->update([
            'status'   => 'ended',
            'ended_at' => now(),
        ]);

        $liveClass->participants()
            ->whereNull('left_at')
            ->update(['left_at' => now()]);

        broadcast(new ClassEnded($liveClass))->toOthers();

        return response()->json(['message' => 'Class ended successfully']);
    }

    /**
     * Handle RTC signaling for establishing peer connections.
     * Subscription is required.
     *
     * @param Request $request
     * @param mixed   $classId
     * @return \Illuminate\Http\JsonResponse
     */
    public function signal(Request $request, $classId)
    {
        if (!$this->checkSubscription()) {
            return response()->json(['message' => 'Active subscription required to access live classes. Please subscribe to continue.'], 403);
        }

        $validated = $request->validate([
            'to_user_id'  => 'required|string',
            'signal_data' => 'required'
        ]);

        broadcast(new RTCSignaling(
            $classId,
            auth()->id(),
            $validated['to_user_id'],
            $validated['signal_data']
        ))->toOthers();

        return response()->json(['status' => 'success']);
    }

    /**
     * Send an ICE candidate for WebRTC connection setup.
     * Subscription is required.
     *
     * @param Request $request
     * @param mixed   $classId
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendIceCandidate(Request $request, $classId)
    {
        if (!$this->checkSubscription()) {
            return response()->json(['message' => 'Active subscription required to access live classes. Please subscribe to continue.'], 403);
        }

        $validated = $request->validate([
            'to_user_id' => 'required|string',
            'candidate'  => 'required'
        ]);

        broadcast(new IceCandidateSignal(
            $classId,
            auth()->id(),
            $validated['to_user_id'],
            $validated['candidate']
        ))->toOthers();

        return response()->json(['status' => 'success']);
    }

    /**
     * Get the current room status including active participants and settings.
     * Subscription is required.
     *
     * @param LiveClass $liveClass
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRoomStatus(LiveClass $liveClass)
    {
        if (!$this->checkSubscription()) {
            return response()->json(['message' => 'Active subscription required to access live classes. Please subscribe to continue.'], 403);
        }

        return response()->json([
            'status'       => $liveClass->status,
            'active_peers' => $liveClass->activeParticipants()
                ->select('user_id', 'role', 'preferences')
                ->with('user:id,username')
                ->get(),
            'settings'     => $liveClass->settings
        ]);
    }

    /**
     * Update the settings for a participant.
     * Subscription is required.
     *
     * @param Request   $request
     * @param LiveClass $liveClass
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateParticipantSettings(Request $request, LiveClass $liveClass)
    {
        if (!$this->checkSubscription()) {
            return response()->json(['message' => 'Active subscription required to access live classes. Please subscribe to continue.'], 403);
        }

        $validated = $request->validate([
            'preferences'              => 'required|array',
            'preferences.video'        => 'boolean',
            'preferences.audio'        => 'boolean',
            'preferences.screen_share' => 'boolean'
        ]);

        $participant = $liveClass->participants()
            ->where('user_id', auth()->id())
            ->first();

        if (!$participant) {
            return response()->json(['message' => 'Not a participant of this class'], 403);
        }

        $participant->update([
            'preferences' => $validated['preferences']
        ]);

        broadcast(new ParticipantSettingsUpdated(
            $liveClass->id,
            auth()->id(),
            $validated['preferences']
        ))->toOthers();

        return response()->json(['status' => 'success']);
    }

    /**
     * Get a list of active participants in the class.
     * Subscription is required.
     *
     * @param LiveClass $liveClass
     * @return \Illuminate\Http\JsonResponse
     */
    public function getParticipants(LiveClass $liveClass)
    {
        if (!$this->checkSubscription()) {
            return response()->json(['message' => 'Active subscription required to access live classes. Please subscribe to continue.'], 403);
        }

        return response()->json([
            'participants' => $liveClass->activeParticipants()
                ->with('user:id,username,first_name,last_name')
                ->get()
        ]);
    }

    /**
     * Start the live stream for a class.
     * Subscription is required and only moderators can start the stream.
     *
     * @param LiveClass $liveClass
     * @return \Illuminate\Http\JsonResponse
     */
    public function startStream(LiveClass $liveClass)
    {
        if (!$this->checkSubscription()) {
            return response()->json(['message' => 'Active subscription required to access live classes. Please subscribe to continue.'], 403);
        }

        $participant = $liveClass->participants()
            ->where('user_id', auth()->id())
            ->first();

        if (!$participant || $participant->role !== 'moderator') {
            return response()->json(['message' => 'Only moderators can start the stream'], 403);
        }

        $liveClass->update([
            'status' => 'live'
        ]);

        broadcast(new StreamStarted($liveClass))->toOthers();

        return response()->json([
            'message'     => 'Stream started',
            'stream_info' => [
                'class_id'   => $liveClass->id,
                'meeting_id' => $liveClass->meeting_id,
                'started_at' => now()
            ]
        ]);
    }

    /**
     * Stop the live stream for a class.
     * Subscription is required and only moderators can stop the stream.
     *
     * @param LiveClass $liveClass
     * @return \Illuminate\Http\JsonResponse
     */
    public function stopStream(LiveClass $liveClass)
    {
        if (!$this->checkSubscription()) {
            return response()->json(['message' => 'Active subscription required to access live classes. Please subscribe to continue.'], 403);
        }

        $participant = $liveClass->participants()
            ->where('user_id', auth()->id())
            ->first();

        if (!$participant || $participant->role !== 'moderator') {
            return response()->json(['message' => 'Only moderators can stop the stream'], 403);
        }

        $liveClass->update([
            'status'   => 'ended',
            'ended_at' => now()
        ]);

        broadcast(new StreamEnded($liveClass))->toOthers();

        return response()->json(['message' => 'Stream ended']);
    }

    /**
     * Get current streaming information.
     * Subscription is required.
     *
     * @param LiveClass $liveClass
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStreamInfo(LiveClass $liveClass)
    {
        if (!$this->checkSubscription()) {
            return response()->json(['message' => 'Active subscription required to access live classes. Please subscribe to continue.'], 403);
        }

        return response()->json([
            'stream_info' => [
                'class_id'     => $liveClass->id,
                'meeting_id'   => $liveClass->meeting_id,
                'status'       => $liveClass->status,
                'participants' => $liveClass->activeParticipants()
                    ->with('user:id,username')
                    ->get()
            ]
        ]);
    }

    /**
     * Report connection quality during the class.
     * Subscription is required.
     *
     * @param Request   $request
     * @param LiveClass $liveClass
     * @return \Illuminate\Http\JsonResponse
     */
    public function reportConnectionQuality(Request $request, LiveClass $liveClass)
    {
        if (!$this->checkSubscription()) {
            return response()->json(['message' => 'Active subscription required to access live classes. Please subscribe to continue.'], 403);
        }

        $validated = $request->validate([
            'quality_metrics'            => 'required|array',
            'quality_metrics.bitrate'    => 'numeric',
            'quality_metrics.packetsLost'=> 'numeric',
            'quality_metrics.latency'    => 'numeric'
        ]);

        \Log::info('Connection Quality Report', [
            'class_id' => $liveClass->id,
            'user_id'  => auth()->id(),
            'metrics'  => $validated['quality_metrics']
        ]);

        if ($validated['quality_metrics']['packetsLost'] > 50) {
            broadcast(new ConnectionQualityWarning(
                $liveClass->id,
                auth()->id()
            ))->toOthers();
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Check the subscription status of the current user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkSubscriptionStatus()
    {
        $subscription = Subscription::where('user_id', auth()->id())
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();

        if (!$subscription) {
            return response()->json([
                'has_subscription'      => false,
                'message'               => 'No active subscription found',
                'subscription_required' => true
            ]);
        }

        return response()->json([
            'has_subscription' => true,
            'subscription'     => [
                'plan_type'      => $subscription->plan_type,
                'expires_at'     => $subscription->expires_at,
                'days_remaining' => now()->diffInDays($subscription->expires_at),
                'is_active'      => $subscription->is_active
            ]
        ]);
    }
}