<?php

namespace App\Http\Controllers;

use App\Models\LiveClass;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Subscription;
use App\Models\User;
use App\Models\LiveClassChat;
use Illuminate\Http\Request;
use App\Events\UserJoinedClass;
use App\Events\RTCSignaling;
use App\Events\IceCandidateSignal;
use App\Events\ParticipantSettingsUpdated;
use App\Events\ClassEnded;
use App\Events\StreamStarted;
use App\Events\StreamEnded;
use App\Events\ConnectionQualityWarning;
use App\Events\LiveClassChatMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class LiveClassController extends Controller
{
    /**
     * Check if the user has an active subscription.
     * In local environment, always returns true for development purposes.
     */
    private function checkSubscription()
    {
        // In local/development environment, bypass subscription check
        if (app()->environment('local')) {
            return true;
        }
        
        // In production, check for an actual subscription
        try {
            $subscription = Subscription::where('user_id', auth()->id())
                ->where('is_active', true)
                ->where('expires_at', '>', now())
                ->first();
                
            return $subscription !== null;
        } catch (\Exception $e) {
            // Log the error but allow access in development
            if (app()->environment('production')) {
                \Log::error('Subscription check failed: ' . $e->getMessage());
                return false;
            }
            return true;
        }
    }

    /**
     * List all live classes regardless of status.
     * No subscription is required to view the list.
     */
    public function index(Request $request)
    {
        // Check for course filter
        $courseId = $request->get('course_id');
        $query = LiveClass::with('teacher');
        
        if ($courseId) {
            $query->where('course_id', $courseId);
        }
        
        // Add other filters
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }
        
        if ($request->has('type')) {
            if ($request->get('type') === 'course') {
                $query->courseRelated();
            } elseif ($request->get('type') === 'standalone') {
                $query->standalone();
            }
        }
        
        // For students, only show classes they're enrolled in or public classes
        $user = auth()->user();
        if ($user && $user->role !== User::ROLE_EDUCATOR) {
            $query->where(function($q) use ($user) {
                // Include classes for courses the user is enrolled in
                $enrolledCourseIds = $user->enrolledCourses()->pluck('courses.id')->toArray();
                $q->whereIn('course_id', $enrolledCourseIds)
                  // Or include standalone classes
                  ->orWhereNull('course_id');
            });
        }
        
        // Order by scheduled time
        $liveClasses = $query->orderBy('scheduled_at', 'asc')
            ->paginate($request->get('per_page', 15));
            
        // Add participant counts
        foreach ($liveClasses as $class) {
            $class->participant_count = $class->activeParticipants()->count();
        }

        return response()->json([
            'message' => 'Live classes retrieved successfully',
            'live_classes' => $liveClasses
        ]);
    }

    /**
     * Show details for a specific live class.
     */
    public function show(LiveClass $liveClass)
    {
        $liveClass->load(['teacher', 'activeParticipants.user']);
        
        // If class is course-related, include course details
        if ($liveClass->course_id) {
            $liveClass->load(['course', 'lesson']);
        }
        
        // Check if user is a participant
        $user = auth()->user();
        if ($user) {
            $isParticipant = $liveClass->participants()
                ->where('user_id', $user->id)
                ->whereNull('left_at')
                ->exists();
                
            $liveClass->is_participant = $isParticipant;
            
            // Is the user the teacher?
            $liveClass->is_teacher = $user->id === $liveClass->teacher_id;
        }
        
        return response()->json($liveClass);
    }

    /**
     * Create a new live class.
     * Subscription is required.
     */
    public function store(Request $request)
    {
        if (!$this->checkSubscription()) {
            return response()->json(['message' => 'Active subscription required to create live classes. Please subscribe to continue.'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'scheduled_at' => 'required|date|after:now',
            'course_id' => 'sometimes|nullable|exists:courses,id',
            'lesson_id' => 'sometimes|nullable|exists:course_lessons,id',
            'class_type' => 'sometimes|nullable|in:course-related,standalone',
            'settings' => 'sometimes|nullable|array',
        ]);

        $user = auth()->user();
        
        // If course_id is provided, verify the user is the course owner
        if (isset($validated['course_id'])) {
            $course = Course::findOrFail($validated['course_id']);
            
            if ($course->user_id !== $user->id) {
                return response()->json([
                    'message' => 'You can only create live classes for courses you own'
                ], 403);
            }
            
            // If lesson_id is provided, verify it belongs to the course
            if (isset($validated['lesson_id'])) {
                $lesson = CourseLesson::findOrFail($validated['lesson_id']);
                $section = $lesson->section;
                
                if ($section->course_id !== $course->id) {
                    return response()->json([
                        'message' => 'The specified lesson does not belong to the selected course'
                    ], 400);
                }
            }
        }

        try {
            DB::beginTransaction();
            
            $meetingId = LiveClass::generateMeetingId();
            
            // Prepare settings with defaults if not provided
            $settings = $request->settings ?? [
                'enable_chat' => false,
                'mute_on_join' => false,
                'video_on_join' => false,
                'allow_screen_sharing' => false,
                'enable_hand_raising' => false,
            ];
            
            // Determine class type based on presence of course_id
            $courseId = $validated['course_id'] ?? null;
            $classType = $validated['class_type'] ?? ($courseId ? 'course-related' : 'standalone');

            $liveClass = LiveClass::create([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'scheduled_at' => $validated['scheduled_at'],
                'teacher_id' => $user->id,
                'course_id' => $courseId,
                'lesson_id' => $validated['lesson_id'] ?? null,
                'meeting_id' => $meetingId,
                'settings' => $settings,
                'status' => 'scheduled',
                'class_type' => $classType
            ]);
            
            // Automatically add the creator as a participant with moderator role
            $participant = $liveClass->participants()->create([
                'user_id' => $user->id,
                'role' => 'moderator',
                'joined_at' => now(),
                'preferences' => [
                    'video' => $settings['video_on_join'] ?? false,
                    'audio' => !($settings['mute_on_join'] ?? false),
                    'screen_share' => false
                ]
            ]);
            
            DB::commit();

            $liveClass->load('teacher', 'activeParticipants.user');
            
            // Add course and lesson details if applicable
            if ($liveClass->course_id) {
                $liveClass->load(['course', 'lesson']);
            }

            return response()->json([
                'message' => 'Live class created successfully',
                'live_class' => $liveClass,
                'meeting_id' => $meetingId,
                'participant' => $participant
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create live class: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to create live class: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Join a live class.
     * Subscription is required.
     * Users can join a live class at any time after the scheduled time.
     */
    public function join(LiveClass $liveClass)
    {
        if (!$this->checkSubscription()) {
            return response()->json([
                'message' => 'Active subscription required to access live classes. Please subscribe to continue.',
                'subscription_required' => true
            ], 403);
        }
        
        $user = auth()->user();
        
        // For course-related classes, check if the user is enrolled
        if ($liveClass->course_id && $user->id !== $liveClass->teacher_id) {
            $isEnrolled = $user->isEnrolledIn($liveClass->course);
            
            if (!$isEnrolled) {
                return response()->json([
                    'message' => 'You need to be enrolled in this course to join the live class',
                    'course_id' => $liveClass->course_id
                ], 403);
            }
        }

        // Check if class has ended - can't join ended classes
        if ($liveClass->status === 'ended') {
            return response()->json([
                'message' => 'This class has already ended and cannot be joined',
                'ended_at' => $liveClass->ended_at
            ], 400);
        }

        try {
            // Check if already a participant
            $existingParticipant = $liveClass->participants()
                ->where('user_id', $user->id)
                ->whereNull('left_at')
                ->first();
                
            if ($existingParticipant) {
                return response()->json([
                    'message' => 'You are already a participant in this class',
                    'participant' => $existingParticipant,
                    'class' => $liveClass->load('teacher', 'activeParticipants.user')
                ]);
            }
            
            // Determine role
            $role = $user->id === $liveClass->teacher_id ? 'moderator' : 'participant';
            
            // Create new participant
            $participant = $liveClass->participants()->create([
                'user_id' => $user->id,
                'role' => $role,
                'joined_at' => now(),
                'preferences' => [
                    'video' => $liveClass->settings['video_on_join'] ?? false,
                    'audio' => !($liveClass->settings['mute_on_join'] ?? false),
                    'screen_share' => false
                ]
            ]);

            // Start class if teacher is joining (regardless of scheduled time)
            if ($liveClass->status === 'scheduled' && $user->id === $liveClass->teacher_id) {
                $liveClass->update(['status' => 'live']);
            }

            broadcast(new UserJoinedClass($liveClass, $user, $participant))->toOthers();

            return response()->json([
                'message' => 'Joined successfully',
                'participant' => $participant,
                'class' => $liveClass->load('teacher', 'activeParticipants.user', 'course', 'lesson')
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to join live class: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to join live class: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * End a live class.
     * Only moderators (typically the teacher) can end the class.
     */
    public function end(LiveClass $liveClass)
    {
        $participant = $liveClass->participants()
            ->where('user_id', auth()->id())
            ->first();

        if (!$participant || $participant->role !== 'moderator') {
            return response()->json(['message' => 'Only moderators can end the class'], 403);
        }

        try {
            $liveClass->update([
                'status' => 'ended',
                'ended_at' => now(),
            ]);

            // Mark all participants as left
            $liveClass->participants()
                ->whereNull('left_at')
                ->update(['left_at' => now()]);

            broadcast(new ClassEnded($liveClass))->toOthers();

            return response()->json(['message' => 'Class ended successfully']);
            
        } catch (\Exception $e) {
            Log::error('Failed to end live class: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to end live class: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Leave a live class.
     */
    public function leave(LiveClass $liveClass)
    {
        try {
            $participant = $liveClass->participants()
                ->where('user_id', auth()->id())
                ->whereNull('left_at')
                ->first();
                
            if (!$participant) {
                return response()->json([
                    'message' => 'You are not an active participant in this class'
                ], 400);
            }
            
            $participant->update([
                'left_at' => now()
            ]);
            
            // Send a leave event (could be implemented as a new event)
            
            return response()->json([
                'message' => 'Left the class successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to leave live class: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to leave live class: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle RTC signaling for establishing peer connections.
     * Subscription is required.
     * 
     * @param Request $request
     * @param mixed $liveClass - Can be LiveClass model or class ID string
     * @return \Illuminate\Http\JsonResponse
     */
    public function signal(Request $request, $liveClass)
    {
        if (!$this->checkSubscription()) {
            return response()->json(['message' => 'Active subscription required to access live classes. Please subscribe to continue.'], 403);
        }

        $validated = $request->validate([
            'to_user_id' => 'required|string',
            'signal_data' => 'required'
        ]);
        
        // Extract class ID - handle both model and string inputs
        $classId = $liveClass instanceof LiveClass ? $liveClass->id : $liveClass;
        
        try {
            // Verify the class exists (if ID is passed directly)
            if (!($liveClass instanceof LiveClass)) {
                $class = LiveClass::findOrFail($classId);
            }
            
            broadcast(new RTCSignaling(
                $classId,
                auth()->id(),
                $validated['to_user_id'],
                $validated['signal_data']
            ))->toOthers();
            
            // Also store the signal in the database for clients that might poll rather than use WebSockets
            // This ensures signals can be retrieved even if the client reconnects
            
            return response()->json(['status' => 'success']);
            
        } catch (\Exception $e) {
            Log::error('WebRTC signaling error', [
                'error' => $e->getMessage(),
                'class_id' => $classId,
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process WebRTC signal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send an ICE candidate for WebRTC connection setup.
     * Subscription is required.
     * 
     * @param Request $request
     * @param mixed $liveClass - Can be LiveClass model or class ID string
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendIceCandidate(Request $request, $liveClass)
    {
        if (!$this->checkSubscription()) {
            return response()->json(['message' => 'Active subscription required to access live classes. Please subscribe to continue.'], 403);
        }

        $validated = $request->validate([
            'to_user_id' => 'required|string',
            'candidate' => 'required'
        ]);
        
        // Extract class ID - handle both model and string inputs
        $classId = $liveClass instanceof LiveClass ? $liveClass->id : $liveClass;
        
        try {
            // Verify the class exists (if ID is passed directly)
            if (!($liveClass instanceof LiveClass)) {
                $class = LiveClass::findOrFail($classId);
            }
            
            broadcast(new IceCandidateSignal(
                $classId,
                auth()->id(),
                $validated['to_user_id'],
                $validated['candidate']
            ))->toOthers();
            
            // Also store the ICE candidate in the database for clients that might poll rather than use WebSockets
            // This ensures ICE candidates can be retrieved even if the client reconnects
            
            return response()->json(['status' => 'success']);
            
        } catch (\Exception $e) {
            Log::error('WebRTC ICE candidate error', [
                'error' => $e->getMessage(),
                'class_id' => $classId,
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process ICE candidate: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the current room status including active participants and settings.
     * Subscription is required.
     */
    public function getRoomStatus(LiveClass $liveClass)
    {
        if (!$this->checkSubscription()) {
            return response()->json(['message' => 'Active subscription required to access live classes. Please subscribe to continue.'], 403);
        }

        // Get active participants with their details
        $activeParticipants = $liveClass->activeParticipants()
            ->with('user:id,username,first_name,last_name,avatar,role')
            ->get()
            ->map(function($participant) {
                return [
                    'user_id' => $participant->user_id,
                    'role' => $participant->role,
                    'preferences' => $participant->preferences,
                    'joined_at' => $participant->joined_at,
                    'user' => $participant->user
                ];
            });
            
        // Get recent chat messages
        $recentMessages = $liveClass->chatMessages()
            ->with('user:id,username,first_name,last_name,avatar,role')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->reverse();

        return response()->json([
            'status' => $liveClass->status,
            'active_participants' => $activeParticipants,
            'settings' => $liveClass->settings,
            'recent_messages' => $recentMessages,
            'course' => $liveClass->course_id ? $liveClass->course : null,
            'lesson' => $liveClass->lesson_id ? $liveClass->lesson : null
        ]);
    }

    /**
     * Update the settings for a participant.
     * Subscription is required.
     */
    public function updateParticipantSettings(Request $request, LiveClass $liveClass)
    {
        if (!$this->checkSubscription()) {
            return response()->json(['message' => 'Active subscription required to access live classes. Please subscribe to continue.'], 403);
        }

        $validated = $request->validate([
            'preferences' => 'required|array',
            'preferences.video' => 'boolean',
            'preferences.audio' => 'boolean',
            'preferences.screen_share' => 'boolean'
        ]);

        $participant = $liveClass->participants()
            ->where('user_id', auth()->id())
            ->whereNull('left_at')
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
     * Get live classes for a specific course.
     */
    public function getClassesForCourse($courseId)
    {
        $course = Course::findOrFail($courseId);
        
        // Check if user has access to the course
        $user = auth()->user();
        $isOwner = $course->user_id === $user->id;
        $isEnrolled = $user->isEnrolledIn($course);
        
        if (!$isOwner && !$isEnrolled) {
            return response()->json([
                'message' => 'You need to be enrolled in this course to access its live classes'
            ], 403);
        }
        
        // Get live classes for this course
        $liveClasses = LiveClass::where('course_id', $courseId)
            ->with('teacher:id,username,first_name,last_name,avatar,role')
            ->orderBy('scheduled_at', 'desc')
            ->get();
            
        // Add participant count to each class
        foreach ($liveClasses as $class) {
            $class->participant_count = $class->activeParticipants()->count();
            $class->is_participant = $class->participants()
                ->where('user_id', $user->id)
                ->whereNull('left_at')
                ->exists();
        }
        
        return response()->json([
            'live_classes' => $liveClasses,
            'course' => [
                'id' => $course->id,
                'title' => $course->title
            ]
        ]);
    }

    /**
     * Get live classes where the user is a teacher.
     */
    public function getMyClasses(Request $request)
    {
        $user = auth()->user();
        $status = $request->get('status', 'all');
        
        $query = LiveClass::where('teacher_id', $user->id);
        
        // Filter by status if specified
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        
        // Filter by course if specified
        if ($request->has('course_id')) {
            $query->where('course_id', $request->get('course_id'));
        }
        
        // Get upcoming or past classes
        if ($request->has('upcoming') && $request->get('upcoming')) {
            $query->where('scheduled_at', '>', now())
                  ->orderBy('scheduled_at', 'asc');
        } else if ($request->has('past') && $request->get('past')) {
            $query->where(function($q) {
                $q->where('status', 'ended')
                  ->orWhere('scheduled_at', '<', now());
            })
            ->orderBy('scheduled_at', 'desc');
        } else {
            // Default: order by scheduled_at descending
            $query->orderBy('scheduled_at', 'desc');
        }
        
        $liveClasses = $query->with('course:id,title,thumbnail_url')
            ->paginate($request->get('per_page', 15));
            
        // Add participant count
        foreach ($liveClasses as $class) {
            $class->participant_count = $class->activeParticipants()->count();
        }
        
        return response()->json([
            'live_classes' => $liveClasses
        ]);
    }

    /**
     * Get live classes where the user is a student/participant.
     */
    public function getEnrolledClasses(Request $request)
    {
        $user = auth()->user();
        $status = $request->get('status', 'all');
        
        // Get IDs of classes where the user is a participant
        $participatedClassIds = $user->liveClassParticipation()
            ->pluck('live_class_id')
            ->toArray();
            
        // Get course IDs where the user is enrolled
        $enrolledCourseIds = $user->enrolledCourses()
            ->pluck('courses.id')
            ->toArray();
            
        $query = LiveClass::where(function($q) use ($participatedClassIds, $enrolledCourseIds) {
            // Include classes where the user participated
            $q->whereIn('id', $participatedClassIds)
              // Or classes for courses the user is enrolled in
              ->orWhereIn('course_id', $enrolledCourseIds);
        });
        
        // Filter by status if specified
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        
        // Get upcoming or past classes
        if ($request->has('upcoming') && $request->get('upcoming')) {
            $query->where('scheduled_at', '>', now())
                  ->orderBy('scheduled_at', 'asc');
        } else if ($request->has('past') && $request->get('past')) {
            $query->where(function($q) {
                $q->where('status', 'ended')
                  ->orWhere('scheduled_at', '<', now());
            })
            ->orderBy('scheduled_at', 'desc');
        } else {
            // Default: order by scheduled_at descending
            $query->orderBy('scheduled_at', 'desc');
        }
        
        $liveClasses = $query->with(['teacher:id,username,first_name,last_name,avatar,role', 
                                     'course:id,title,thumbnail_url'])
            ->paginate($request->get('per_page', 15));
            
        // Add participant status
        foreach ($liveClasses as $class) {
            $class->is_participant = $class->participants()
                ->where('user_id', $user->id)
                ->whereNull('left_at')
                ->exists();
        }
        
        return response()->json([
            'live_classes' => $liveClasses
        ]);
    }

    /**
     * Update a live class (only by teacher).
     */
    public function update(Request $request, LiveClass $liveClass)
    {
        // Check if user is the teacher
        if (auth()->id() !== $liveClass->teacher_id) {
            return response()->json([
                'message' => 'Only the teacher can update this class'
            ], 403);
        }
        
        // Can't update a class that has already ended
        if ($liveClass->status === 'ended') {
            return response()->json([
                'message' => 'Cannot update a class that has already ended'
            ], 400);
        }
        
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'scheduled_at' => 'sometimes|date|after:now',
            'settings' => 'nullable|array',
        ]);
        
        try {
            // If course_id or lesson_id are being updated, check ownership
            if ($request->has('course_id') || $request->has('lesson_id')) {
                if ($request->has('course_id')) {
                    $course = Course::findOrFail($request->course_id);
                    
                    if ($course->user_id !== auth()->id()) {
                        return response()->json([
                            'message' => 'You can only link classes to courses you own'
                        ], 403);
                    }
                }
                
                if ($request->has('lesson_id')) {
                    $lesson = CourseLesson::findOrFail($request->lesson_id);
                    $courseId = $request->has('course_id') ? $request->course_id : $liveClass->course_id;
                    
                    if ($lesson->section->course_id != $courseId) {
                        return response()->json([
                            'message' => 'The lesson must belong to the selected course'
                        ], 400);
                    }
                }
            }
            
            // Merge settings rather than replace
            if (isset($validated['settings'])) {
                $settings = array_merge($liveClass->settings ?? [], $validated['settings']);
                $validated['settings'] = $settings;
            }
            
            // Update class
            $liveClass->update($validated);
            
            // Load relationships
            $liveClass->load('teacher');
            if ($liveClass->course_id) {
                $liveClass->load(['course', 'lesson']);
            }
            
            return response()->json([
                'message' => 'Live class updated successfully',
                'live_class' => $liveClass
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update live class: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to update live class: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a live class (only by teacher, and only if it hasn't started).
     */
    public function destroy(LiveClass $liveClass)
    {
        // Check if user is the teacher
        if (auth()->id() !== $liveClass->teacher_id) {
            return response()->json([
                'message' => 'Only the teacher can delete this class'
            ], 403);
        }
        
        // Can't delete a class that has already started or ended
        if ($liveClass->status !== 'scheduled') {
            return response()->json([
                'message' => 'Cannot delete a class that has already started or ended'
            ], 400);
        }
        
        try {
            // Delete participants (cascade via foreign key)
            $liveClass->delete();
            
            return response()->json([
                'message' => 'Live class deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to delete live class: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to delete live class: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Start the live stream for a class.
     * Subscription is required and only moderators can start the stream.
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
            'message' => 'Stream started',
            'stream_info' => [
                'class_id' => $liveClass->id,
                'meeting_id' => $liveClass->meeting_id,
                'started_at' => now()
            ]
        ]);
    }

    /**
     * Stop the live stream for a class.
     * Subscription is required and only moderators can stop the stream.
     * When a stream is ended, the live class is deleted.
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

        try {
            // First update the status to ended for the broadcast event
            $liveClass->update([
                'status' => 'ended',
                'ended_at' => now()
            ]);

            // Broadcast that the stream has ended
            broadcast(new StreamEnded($liveClass))->toOthers();
            
            // Get the class ID for the response
            $classId = $liveClass->id;
            
            // Delete participants first (foreign key constraints)
            $liveClass->participants()->delete();
            
            // Delete any chat messages
            if (method_exists($liveClass, 'chatMessages')) {
                $liveClass->chatMessages()->delete();
            }
            
            // Delete the live class
            $liveClass->delete();
            
            return response()->json([
                'message' => 'Stream ended and class deleted',
                'class_id' => $classId
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to delete live class after ending stream: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Stream ended but failed to delete class: ' . $e->getMessage(),
                'class_id' => $liveClass->id
            ]);
        }
    }
    
    /**
     * Get stream information for a live class.
     * Includes active participants, stream status, and settings.
     * If teacher accesses this endpoint and class isn't started, it automatically marks the stream as started.
     */
    public function getStreamInfo(LiveClass $liveClass)
    {
        if (!$this->checkSubscription()) {
            return response()->json(['message' => 'Active subscription required to access live classes. Please subscribe to continue.'], 403);
        }
        
        // Check if user is a participant
        $participant = $liveClass->participants()
            ->where('user_id', auth()->id())
            ->first();
            
        if (!$participant) {
            return response()->json([
                'message' => 'You are not a participant in this class',
                'join_required' => true
            ], 403);
        }
        
        // Auto-start stream if teacher is accessing and the class isn't started yet
        $user = auth()->user();
        $isTeacher = $user->id === $liveClass->teacher_id;
        $isModeratorOrTeacher = $participant->role === 'moderator' || $isTeacher;
        
        // If teacher/moderator is viewing and class is in scheduled status, start it
        if ($isModeratorOrTeacher && $liveClass->status === 'scheduled') {
            $liveClass->update(['status' => 'live']);
            
            // Broadcast that the stream has started
            try {
                broadcast(new StreamStarted($liveClass))->toOthers();
                Log::info("Stream automatically started by teacher viewing stream info", ['class_id' => $liveClass->id]);
            } catch (\Exception $e) {
                Log::error("Failed to broadcast stream start", ['error' => $e->getMessage()]);
            }
        }
        
        // Get active participants (refresh after potential status update)
        $activeParticipants = $liveClass->activeParticipants()
            ->with('user:id,username,first_name,last_name,avatar,role')
            ->get();
        
        // Get stream settings and status (reload after potential update)
        $liveClass = LiveClass::find($liveClass->id); // Refresh the model
            
        // Get stream settings and status
        $streamInfo = [
            'class_id' => $liveClass->id,
            'meeting_id' => $liveClass->meeting_id,
            'status' => $liveClass->status,
            'started_at' => $liveClass->status === 'live' ? $liveClass->updated_at : null,
            'ended_at' => $liveClass->ended_at,
            'settings' => $liveClass->settings,
            'active_participants' => $activeParticipants,
            'is_moderator' => $participant->role === 'moderator',
            'current_user' => [
                'participant_id' => $participant->id,
                'role' => $participant->role,
                'preferences' => $participant->preferences
            ]
        ];
        
        return response()->json($streamInfo);
    }

    /**
     * Send a message in a live class chat.
     * User must be a participant in the class.
     */
    public function sendChatMessage(Request $request, $classId)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
        ]);
        
        $liveClass = LiveClass::findOrFail($classId);
        $user = auth()->user();
        
        // Check if user is a participant
        $isParticipant = $liveClass->participants()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->exists();
            
        if (!$isParticipant) {
            return response()->json([
                'message' => 'You must be a participant to send messages in this class',
                'join_required' => true
            ], 403);
        }
        
        // Check if chat is enabled in class settings
        if (!($liveClass->settings['enable_chat'] ?? false)) {
            return response()->json([
                'message' => 'Chat is disabled for this class'
            ], 403);
        }
        
        try {
            $chatMessage = LiveClassChat::create([
                'live_class_id' => $liveClass->id,
                'user_id' => $user->id,
                'message' => $validated['message'],
                'type' => 'text',
            ]);
            
            $chatMessage->load('user:id,username,first_name,last_name,avatar,role');
            
            // Broadcast to other participants
            broadcast(new LiveClassChatMessage($chatMessage))->toOthers();
            
            return response()->json([
                'message' => 'Message sent successfully',
                'chat_message' => $chatMessage
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send live class chat message: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to send message: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get chat history for a live class.
     * User must be a participant in the class.
     */
    public function getChatHistory($classId)
    {
        $liveClass = LiveClass::findOrFail($classId);
        $user = auth()->user();
        
        // Check if user is a participant
        $isParticipant = $liveClass->participants()
            ->where('user_id', $user->id)
            ->exists();
            
        if (!$isParticipant) {
            return response()->json([
                'message' => 'You must be a participant to view chat history',
                'join_required' => true
            ], 403);
        }
        
        // Get chat messages with user info
        $chatMessages = $liveClass->chatMessages()
            ->with('user:id,username,first_name,last_name,avatar,role')
            ->orderBy('created_at')
            ->get();
            
        return response()->json([
            'chat_messages' => $chatMessages,
            'chat_enabled' => $liveClass->settings['enable_chat'] ?? false
        ]);
    }
    
    /**
     * Delete a chat message.
     * Only the message author or a moderator can delete a message.
     */
    public function deleteChatMessage($messageId)
    {
        $chatMessage = LiveClassChat::findOrFail($messageId);
        $liveClass = LiveClass::findOrFail($chatMessage->live_class_id);
        $user = auth()->user();
        
        // Check if user is the message author or a moderator
        $participant = $liveClass->participants()
            ->where('user_id', $user->id)
            ->first();
            
        if (!$participant) {
            return response()->json([
                'message' => 'You are not a participant in this class'
            ], 403);
        }
        
        $isAuthor = $chatMessage->user_id === $user->id;
        $isModerator = $participant->role === 'moderator';
        
        if (!$isAuthor && !$isModerator) {
            return response()->json([
                'message' => 'You can only delete your own messages'
            ], 403);
        }
        
        try {
            $chatMessage->delete();
            
            return response()->json([
                'message' => 'Message deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to delete live class chat message: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to delete message: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Poll for WebRTC signals for iOS compatibility.
     * This endpoint allows iOS clients to poll for signals instead of using WebSockets.
     * 
     * @param string $classId The ID of the live class
     * @param string $userId The ID of the user to get signals for
     * @return \Illuminate\Http\JsonResponse
     */
    public function pollSignals($classId, $userId)
    {
        if (!$this->checkSubscription()) {
            return response()->json(['message' => 'Active subscription required to access live classes. Please subscribe to continue.'], 403);
        }
        
        try {
            // Verify the class exists
            $liveClass = LiveClass::findOrFail($classId);
            
            // Verify the user is a participant
            $participant = $liveClass->participants()
                ->where('user_id', auth()->id())
                ->first();
                
            if (!$participant) {
                return response()->json([
                    'message' => 'You are not a participant in this class',
                    'join_required' => true
                ], 403);
            }
            
            // For real-time WebRTC signals, we're actually using WebSockets behind the scenes
            // This endpoint is a fallback for iOS clients that are polling instead
            // Since our live WebRTC system uses direct broadcasting, we'll provide empty signals
            // The actual signaling is happening through broadcast events
            
            Log::info('iOS client polling for signals', [
                'class_id' => $classId,
                'from_user_id' => $userId,
                'to_user_id' => auth()->id()
            ]);
            
            // Return empty signals - the actual WebRTC signaling happens via WebSockets/broadcasting
            // This endpoint just satisfies the iOS client's polling expectations
            return response()->json([
                'signals' => []
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to poll signals', [
                'error' => $e->getMessage(),
                'class_id' => $classId,
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'message' => 'Failed to poll signals: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check the subscription status of the current user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkSubscriptionStatus()
    {
        // In local environment, return fake subscription for development
        if (app()->environment('local')) {
            return response()->json([
                'has_subscription' => true,
                'subscription' => [
                    'plan_type' => 'development',
                    'expires_at' => now()->addYear(),
                    'days_remaining' => 365,
                    'is_active' => true
                ]
            ]);
        }

        // In production, check actual subscription
        try {
            $subscription = Subscription::where('user_id', auth()->id())
                ->where('is_active', true)
                ->where('expires_at', '>', now())
                ->first();

            if (!$subscription) {
                return response()->json([
                    'has_subscription' => false,
                    'message' => 'No active subscription found',
                    'subscription_required' => true,
                    'plans' => [
                        [
                            'type' => 'monthly',
                            'price' => 5000,
                            'features' => [
                                'Access to all live classes',
                                'Chat during classes',
                                'Screen sharing',
                                'HD video quality'
                            ]
                        ],
                        [
                            'type' => 'yearly',
                            'price' => 50000,
                            'features' => [
                                'All monthly features',
                                '2 months free',
                                'Priority support',
                                'Recording access'
                            ]
                        ]
                    ]
                ]);
            }

            return response()->json([
                'has_subscription' => true,
                'subscription' => [
                    'plan_type' => $subscription->plan_type,
                    'expires_at' => $subscription->expires_at,
                    'days_remaining' => now()->diffInDays($subscription->expires_at),
                    'is_active' => $subscription->is_active
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error checking subscription status: ' . $e->getMessage());
            
            // In non-production, provide a fallback subscription
            if (!app()->environment('production')) {
                return response()->json([
                    'has_subscription' => true,
                    'subscription' => [
                        'plan_type' => 'development',
                        'expires_at' => now()->addYear(),
                        'days_remaining' => 365,
                        'is_active' => true
                    ]
                ]);
            }
            
            return response()->json([
                'has_subscription' => false,
                'message' => 'Error checking subscription status',
                'subscription_required' => true
            ]);
        }
    }
    
    /**
     * Manual cleanup of expired and overdue live classes (Admin only).
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cleanupExpired(Request $request)
    {
        // Check if user is admin
        if (!auth()->user()->is_admin) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }
        
        $days = $request->get('days', 1);
        $hours = $request->get('hours', 1);
        
        try {
            $results = LiveClass::cleanupAll($days, $hours);
            
            return response()->json([
                'success' => true,
                'message' => "Successfully cleaned up {$results['total_cleaned']} live class(es).",
                'results' => [
                    'expired_cleaned' => $results['expired_cleaned'],
                    'overdue_cleaned' => $results['overdue_cleaned'],
                    'total_cleaned' => $results['total_cleaned']
                ],
                'thresholds' => [
                    'days_after_end' => $days,
                    'hours_past_scheduled' => $hours
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Manual live class cleanup failed', [
                'error' => $e->getMessage(),
                'admin_user' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup live classes.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}