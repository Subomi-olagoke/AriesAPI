<?php

namespace App\Http\Controllers;

use App\Models\CourseLesson;
use App\Models\CourseSection;
use App\Services\CourseLessonService;
use Illuminate\Http\Request;

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
        
        // Get files from request
        $videoFile = $request->hasFile('video') ? $request->file('video') : null;
        $documentFile = $request->hasFile('file') ? $request->file('file') : null;
        $thumbnailFile = $request->hasFile('thumbnail') ? $request->file('thumbnail') : null;
        
        $result = $this->lessonService->createLesson(
            auth()->user(),
            $section,
            $request->only([
                'title', 'description', 'content_type', 'duration_minutes',
                'order', 'is_preview', 'quiz_data', 'assignment_data'
            ]),
            $videoFile,
            $documentFile,
            $thumbnailFile
        );
        
        return response()->json([
            'message' => $result['message'],
            'lesson' => $result['success'] ? $result['lesson'] : null
        ], $result['code']);
    }
    
    /**
     * Display a lesson
     */
    public function show($lessonId)
    {
        $lesson = CourseLesson::findOrFail($lessonId);
        
        $result = $this->lessonService->getLesson(
            $lesson,
            auth()->user()
        );
        
        if ($result['success']) {
            return response()->json([
                'lesson' => $result['lesson']
            ]);
        } else {
            return response()->json([
                'message' => $result['message']
            ], $result['code']);
        }
    }
    
    /**
     * Update a lesson
     */
    public function update(Request $request, $lessonId)
    {
        $lesson = CourseLesson::findOrFail($lessonId);
        
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
        
        // Get files from request
        $videoFile = $request->hasFile('video') ? $request->file('video') : null;
        $documentFile = $request->hasFile('file') ? $request->file('file') : null;
        $thumbnailFile = $request->hasFile('thumbnail') ? $request->file('thumbnail') : null;
        
        $result = $this->lessonService->updateLesson(
            auth()->user(),
            $lesson,
            $request->only([
                'title', 'description', 'content_type', 'duration_minutes',
                'order', 'is_preview', 'quiz_data', 'assignment_data'
            ]),
            $videoFile,
            $documentFile,
            $thumbnailFile
        );
        
        return response()->json([
            'message' => $result['message'],
            'lesson' => $result['success'] ? $result['lesson'] : null
        ], $result['code']);
    }
    
    /**
     * Delete a lesson
     */
    public function destroy($lessonId)
    {
        $lesson = CourseLesson::findOrFail($lessonId);
        
        $result = $this->lessonService->deleteLesson(
            auth()->user(),
            $lesson
        );
        
        return response()->json([
            'message' => $result['message']
        ], $result['code']);
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
            $progress = ($completedLessons / $totalLessons) * 100;
            $enrollment->updateProgress($progress);
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