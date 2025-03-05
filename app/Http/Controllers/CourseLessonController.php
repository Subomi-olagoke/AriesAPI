<?php

namespace App\Http\Controllers;

use App\Models\CourseLesson;
use App\Models\CourseSection;
use App\Services\CourseLessonService;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CourseLessonController extends Controller
{
    protected $lessonService;
    
    public function __construct(CourseLessonService $lessonService)
    {
        $this->lessonService = $lessonService;
    }
    
    /**
     * Create a new lesson for a course section
     */
    public function store(Request $request, $sectionId)
    {
        $section = CourseSection::findOrFail($sectionId);
        
        // Check if user is the course owner
        if (auth()->id() !== $section->course->user_id) {
            return response()->json([
                'message' => 'You are not authorized to modify this course'
            ], 403);
        }
        
        $this->validate($request, [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content_type' => 'required|in:video,document,quiz,assignment',
            'duration_minutes' => 'nullable|integer|min:1',
            'order' => 'nullable|integer|min:0',
            'is_preview' => 'nullable|boolean',
            'video' => 'required_if:content_type,video|file|mimes:mp4,mov,avi,webm,mkv|max:2097152', // 2GB max
            'file' => 'required_if:content_type,document|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip|max:102400', // 100MB max
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:5120', // 5MB max
            'quiz_data' => 'required_if:content_type,quiz|array',
            'assignment_data' => 'required_if:content_type,assignment|array'
        ]);
        
        try {
            DB::beginTransaction();
            
            // Create new lesson
            $lesson = new CourseLesson([
                'section_id' => $section->id,
                'title' => $request->title,
                'description' => $request->description ?? null,
                'content_type' => $request->content_type,
                'duration_minutes' => $request->duration_minutes ?? null,
                'order' => $request->order ?? null, // Will be auto-assigned if null
                'is_preview' => $request->is_preview ?? false
            ]);
            
            // Add quiz data if provided and content type is quiz
            if ($lesson->content_type === 'quiz' && isset($request->quiz_data)) {
                $lesson->quiz_data = $request->quiz_data;
            }
            
            // Add assignment data if provided and content type is assignment
            if ($lesson->content_type === 'assignment' && isset($request->assignment_data)) {
                $lesson->assignment_data = $request->assignment_data;
            }
            
            $fileUploadService = app(FileUploadService::class);
            
            // Handle thumbnail upload if provided
            if ($request->hasFile('thumbnail')) {
                $lesson->thumbnail_url = $fileUploadService->uploadFile(
                    $request->file('thumbnail'),
                    'lesson_thumbnails',
                    [
                        'process_image' => true,
                        'width' => 640,
                        'height' => 360,
                        'fit' => true
                    ]
                );
            }
            
            // Handle video upload if provided
            if ($request->hasFile('video')) {
                $lesson->video_url = $fileUploadService->uploadFile(
                    $request->file('video'),
                    'lesson_videos'
                );
            }
            
            // Handle file upload if provided
            if ($request->hasFile('file')) {
                $lesson->file_url = $fileUploadService->uploadFile(
                    $request->file('file'),
                    'lesson_files'
                );
            }
            
            $lesson->save();
            
            // Update course duration if provided
            if (isset($request->duration_minutes) && $request->duration_minutes > 0) {
                $totalDuration = CourseLesson::where('section_id', $section->id)->sum('duration_minutes');
                $section->course->duration_minutes = $totalDuration;
                $section->course->save();
            }
            
            DB::commit();
            
            return response()->json([
                'message' => 'Lesson created successfully',
                'lesson' => $lesson
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lesson creation failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Lesson creation failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Display a lesson
     */
    public function show($lessonId)
    {
        $lesson = CourseLesson::findOrFail($lessonId);
        
        // Get course
        $course = $lesson->section->course;
        
        // Check if user can access this lesson
        $isPreview = $lesson->is_preview;
        $isEnrolled = false;
        $isOwner = false;
        
        if (auth()->check()) {
            $user = auth()->user();
            $isEnrolled = $course->isUserEnrolled($user);
            $isOwner = $user->id === $course->user_id;
        }
        
        $isAccessible = $isPreview || $isEnrolled || $isOwner;
        
        // If not accessible, return limited info
        if (!$isAccessible) {
            return response()->json([
                'message' => 'Lesson requires enrollment',
                'lesson' => [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'description' => $lesson->description,
                    'thumbnail_url' => $lesson->thumbnail_url,
                    'content_type' => $lesson->content_type,
                    'duration_minutes' => $lesson->duration_minutes,
                    'is_preview' => $lesson->is_preview,
                    'is_accessible' => false,
                    'course' => [
                        'id' => $course->id,
                        'title' => $course->title
                    ],
                    'section' => [
                        'id' => $lesson->section->id,
                        'title' => $lesson->section->title
                    ]
                ]
            ], 200);
        }
        
        // For accessible lessons, return full details
        $completed = false;
        if (auth()->check() && $isEnrolled) {
            $user = auth()->user();
            $progress = $user->lessonProgress()
                ->where('lesson_id', $lesson->id)
                ->first();
            $completed = $progress ? $progress->completed : false;
        }
        
        return response()->json([
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'description' => $lesson->description,
                'video_url' => $lesson->video_url,
                'file_url' => $lesson->file_url,
                'thumbnail_url' => $lesson->thumbnail_url,
                'content_type' => $lesson->content_type,
                'duration_minutes' => $lesson->duration_minutes,
                'order' => $lesson->order,
                'quiz_data' => $lesson->quiz_data,
                'assignment_data' => $lesson->assignment_data,
                'is_preview' => $lesson->is_preview,
                'is_accessible' => true,
                'completed' => $completed,
                'course' => [
                    'id' => $course->id,
                    'title' => $course->title
                ],
                'section' => [
                    'id' => $lesson->section->id,
                    'title' => $lesson->section->title
                ]
            ]
        ], 200);
    }
    
    /**
     * Update a lesson
     */
    public function update(Request $request, $lessonId)
    {
        $lesson = CourseLesson::findOrFail($lessonId);
        
        // Check if user is the course owner
        if (auth()->id() !== $lesson->section->course->user_id) {
            return response()->json([
                'message' => 'You are not authorized to modify this course'
            ], 403);
        }
        
        $this->validate($request, [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'content_type' => 'sometimes|in:video,document,quiz,assignment',
            'duration_minutes' => 'nullable|integer|min:1',
            'order' => 'nullable|integer|min:0',
            'is_preview' => 'nullable|boolean',
            'video' => 'nullable|file|mimes:mp4,mov,avi,webm,mkv|max:2097152',
            'file' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip|max:102400',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
            'quiz_data' => 'nullable|array',
            'assignment_data' => 'nullable|array'
        ]);
        
        try {
            DB::beginTransaction();
            
            // Update basic properties if provided
            if (isset($request->title)) {
                $lesson->title = $request->title;
            }
            
            if (isset($request->description)) {
                $lesson->description = $request->description;
            }
            
            if (isset($request->content_type)) {
                $lesson->content_type = $request->content_type;
            }
            
            if (isset($request->duration_minutes)) {
                $lesson->duration_minutes = $request->duration_minutes;
            }
            
            if (isset($request->is_preview)) {
                $lesson->is_preview = $request->is_preview;
            }
            
            // Only update order if explicitly provided
            if (isset($request->order)) {
                // Get all other lessons in this section
                $otherLessons = CourseLesson::where('section_id', $lesson->section_id)
                    ->where('id', '!=', $lesson->id)
                    ->orderBy('order')
                    ->get();
                
                $order = 0;
                $newOrder = $request->order;
                
                // Reorder other lessons
                foreach ($otherLessons as $otherLesson) {
                    if ($order == $newOrder) {
                        $order++;
                    }
                    $otherLesson->order = $order;
                    $otherLesson->save();
                    $order++;
                }
                
                $lesson->order = $newOrder;
            }
            
            // Update quiz data if provided and content type is quiz
            if ($lesson->content_type === 'quiz' && isset($request->quiz_data)) {
                $lesson->quiz_data = $request->quiz_data;
            }
            
            // Update assignment data if provided and content type is assignment
            if ($lesson->content_type === 'assignment' && isset($request->assignment_data)) {
                $lesson->assignment_data = $request->assignment_data;
            }
            
            $fileUploadService = app(FileUploadService::class);
            
            // Handle thumbnail upload if provided
            if ($request->hasFile('thumbnail')) {
                // Delete old thumbnail if it exists
                if ($lesson->thumbnail_url && strpos($lesson->thumbnail_url, 's3.amazonaws.com') !== false) {
                    $oldPath = parse_url($lesson->thumbnail_url, PHP_URL_PATH);
                    if ($oldPath) {
                        Storage::disk('s3')->delete($oldPath);
                    }
                }
                
                $lesson->thumbnail_url = $fileUploadService->uploadFile(
                    $request->file('thumbnail'),
                    'lesson_thumbnails',
                    [
                        'process_image' => true,
                        'width' => 640,
                        'height' => 360,
                        'fit' => true
                    ]
                );
            }
            
            // Handle video upload if provided
            if ($request->hasFile('video')) {
                // Delete old video if it exists
                if ($lesson->video_url && strpos($lesson->video_url, 's3.amazonaws.com') !== false) {
                    $oldPath = parse_url($lesson->video_url, PHP_URL_PATH);
                    if ($oldPath) {
                        Storage::disk('s3')->delete($oldPath);
                    }
                }
                
                $lesson->video_url = $fileUploadService->uploadFile(
                    $request->file('video'),
                    'lesson_videos'
                );
            }
            
            // Handle file upload if provided
            if ($request->hasFile('file')) {
                // Delete old file if it exists
                if ($lesson->file_url && strpos($lesson->file_url, 's3.amazonaws.com') !== false) {
                    $oldPath = parse_url($lesson->file_url, PHP_URL_PATH);
                    if ($oldPath) {
                        Storage::disk('s3')->delete($oldPath);
                    }
                }
                
                $lesson->file_url = $fileUploadService->uploadFile(
                    $request->file('file'),
                    'lesson_files'
                );
            }
            
            $lesson->save();
            
            // Update course duration if changed
            if (isset($request->duration_minutes)) {
                $totalDuration = CourseLesson::whereHas('section', function ($query) use ($lesson) {
                    $query->where('course_id', $lesson->section->course_id);
                })->sum('duration_minutes');
                
                $course = $lesson->section->course;
                $course->duration_minutes = $totalDuration;
                $course->save();
            }
            
            DB::commit();
            
            return response()->json([
                'message' => 'Lesson updated successfully',
                'lesson' => $lesson->fresh()
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lesson update failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Lesson update failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete a lesson
     */
    public function destroy($lessonId)
    {
        $lesson = CourseLesson::findOrFail($lessonId);
        
        // Check if user is the course owner
        if (auth()->id() !== $lesson->section->course->user_id) {
            return response()->json([
                'message' => 'You are not authorized to modify this course'
            ], 403);
        }
        
        try {
            DB::beginTransaction();
            
            // Delete lesson files if they exist
            if ($lesson->video_url && strpos($lesson->video_url, 's3.amazonaws.com') !== false) {
                $videoPath = parse_url($lesson->video_url, PHP_URL_PATH);
                if ($videoPath) {
                    Storage::disk('s3')->delete($videoPath);
                }
            }
            
            if ($lesson->file_url && strpos($lesson->file_url, 's3.amazonaws.com') !== false) {
                $filePath = parse_url($lesson->file_url, PHP_URL_PATH);
                if ($filePath) {
                    Storage::disk('s3')->delete($filePath);
                }
            }
            
            if ($lesson->thumbnail_url && strpos($lesson->thumbnail_url, 's3.amazonaws.com') !== false) {
                $thumbnailPath = parse_url($lesson->thumbnail_url, PHP_URL_PATH);
                if ($thumbnailPath) {
                    Storage::disk('s3')->delete($thumbnailPath);
                }
            }
            
            // Store section_id and course_id for reordering and updating duration
            $sectionId = $lesson->section_id;
            $courseId = $lesson->section->course_id;
            $lessonDuration = $lesson->duration_minutes;
            
            // Delete the lesson
            $lesson->delete();
            
            // Reorder remaining lessons to ensure no gaps
            $remainingLessons = CourseLesson::where('section_id', $sectionId)
                ->orderBy('order')
                ->get();
                
            $order = 0;
            foreach ($remainingLessons as $remainingLesson) {
                $remainingLesson->order = $order;
                $remainingLesson->save();
                $order++;
            }
            
            // Update course duration
            if ($lessonDuration > 0) {
                $course = $lesson->section->course;
                $totalDuration = CourseLesson::whereHas('section', function ($query) use ($courseId) {
                    $query->where('course_id', $courseId);
                })->sum('duration_minutes');
                
                $course->duration_minutes = $totalDuration;
                $course->save();
            }
            
            DB::commit();
            
            return response()->json([
                'message' => 'Lesson deleted successfully'
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lesson deletion failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Lesson deletion failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Mark a lesson as completed for the current user
     */
    public function markComplete(Request $request, $lessonId)
    {
        $lesson = CourseLesson::findOrFail($lessonId);
        $user = auth()->user();
        
        // Check if user is enrolled in the course
        $course = $lesson->section->course;
        if (!$course->isUserEnrolled($user)) {
            return response()->json([
                'message' => 'You need to be enrolled in this course to mark lessons as complete'
            ], 403);
        }
        
        // Create or update progress record
        $progress = $user->lessonProgress()
            ->updateOrCreate(
                ['lesson_id' => $lesson->id],
                ['completed' => true]
            );
            
        // Check if all lessons in course are completed
        $totalLessons = $course->lessons()->count();
        $completedLessons = $user->lessonProgress()
            ->whereIn('lesson_id', $course->lessons()->pluck('id'))
            ->where('completed', true)
            ->count();
            
        // Update enrollment progress percentage
        $enrollment = $user->enrollments()
            ->where('course_id', $course->id)
            ->first();
            
        if ($enrollment) {
            $progressPercent = ($completedLessons / $totalLessons) * 100;
            $enrollment->updateProgress($progressPercent);
        }
        
        return response()->json([
            'message' => 'Lesson marked as completed',
            'progress' => [
                'completed_lessons' => $completedLessons,
                'total_lessons' => $totalLessons,
                'percentage' => $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0
            ]
        ]);
    }
}