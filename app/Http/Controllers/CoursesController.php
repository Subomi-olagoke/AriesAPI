<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Topic;
use App\Services\CourseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CoursesController extends Controller {
    
    protected $courseService;
    
    public function __construct(CourseService $courseService)
    {
        $this->courseService = $courseService;
    }
    
    /**
     * Create a new course with both local and S3 storage options.
     */
    public function createCourse(Request $request) {
        // Validate the request
        $this->validate($request, [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'topic_id' => 'required|exists:topics,id',
            'content_type' => 'required|in:video,file,both',
            'video' => 'required_if:content_type,video,both|file|mimes:mp4,mov,avi,webm,mkv|max:2097152', // 2GB max
            'file' => 'required_if:content_type,file,both|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip|max:102400', // 100MB max
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:5120', // 5MB max
            'duration_minutes' => 'nullable|integer|min:1',
            'difficulty_level' => 'nullable|in:beginner,intermediate,advanced',
            'learning_outcomes' => 'nullable|array',
            'prerequisites' => 'nullable|array',
            'completion_criteria' => 'nullable|array',
        ]);

        // Get files from request
        $videoFile = $request->hasFile('video') ? $request->file('video') : null;
        $documentFile = $request->hasFile('file') ? $request->file('file') : null;
        $thumbnailFile = $request->hasFile('thumbnail') ? $request->file('thumbnail') : null;
        
        // Call service to create the course
        $result = $this->courseService->createCourse(
            auth()->user(),
            $request->only([
                'title', 'description', 'price', 'topic_id', 
                'duration_minutes', 'difficulty_level', 
                'learning_outcomes', 'prerequisites', 'completion_criteria'
            ]),
            $videoFile,
            $documentFile,
            $thumbnailFile
        );
        
        // Return response based on result
        return response()->json([
            'message' => $result['message'],
            'course' => $result['success'] ? $result['course'] : null
        ], $result['code']);
    }

    /**
     * Display the specified course.
     */
    public function viewCourse($id) {
        $course = Course::with(['user', 'topic'])->findOrFail($id);
        
        // Add enrollment information for the current user if authenticated
        if (Auth::check()) {
            $user = Auth::user();
            $course->is_enrolled = $course->isUserEnrolled($user);
            $course->enrollment = $user->enrollments()
                ->where('course_id', $course->id)
                ->first();
        }

        return response()->json([
            'course' => $course
        ]);
    }
    
    /**
     * Get course with all its content
     */
    public function getCourseContent($id) {
        $course = Course::findOrFail($id);
        $user = auth()->user();
        
        // Use service to get course with content
        $result = $this->courseService->getCourseWithContent($course, $user);
        
        if ($result['success']) {
            return response()->json($result['data']);
        } else {
            return response()->json([
                'message' => $result['message']
            ], $result['code']);
        }
    }

    /**
     * List all courses with optional filtering.
     */
    public function listCourses(Request $request) {
        $query = Course::with(['user', 'topic']);
        
        // Filter by topic if provided
        if ($request->has('topic_id')) {
            $query->where('topic_id', $request->topic_id);
        }
        
        // Filter by price range if provided
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }
        
        // Filter free courses if requested
        if ($request->has('free') && $request->free) {
            $query->where('price', 0);
        }
        
        // Filter by difficulty level if provided
        if ($request->has('difficulty_level')) {
            $query->where('difficulty_level', $request->difficulty_level);
        }
        
        // Filter by duration if provided
        if ($request->has('max_duration')) {
            $query->where('duration_minutes', '<=', $request->max_duration);
        }
        
        // Order by popularity, newest, or highest rated
        if ($request->has('order_by')) {
            if ($request->order_by === 'popular') {
                $query->withCount('enrollments')->orderBy('enrollments_count', 'desc');
            } elseif ($request->order_by === 'newest') {
                $query->orderBy('created_at', 'desc');
            } elseif ($request->order_by === 'rating') {
                // If you implement ratings, you could order by average rating here
                $query->orderBy('likes_count', 'desc');
            }
        } else {
            // Default order is newest
            $query->orderBy('created_at', 'desc');
        }
        
        // Paginate the results
        $courses = $query->paginate($request->get('per_page', 12));
        
        // Add extra statistics
        foreach ($courses as $course) {
            $course->lesson_count = $course->lessons()->count();
        }
        
        return response()->json([
            'courses' => $courses
        ]);
    }

    /**
     * Update an existing course.
     */
    public function updateCourse(Request $request, $id) {
        $course = Course::findOrFail($id);
        
        // Validate the request
        $this->validate($request, [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric|min:0',
            'topic_id' => 'sometimes|exists:topics,id',
            'video' => 'sometimes|file|mimes:mp4,mov,avi,webm,mkv|max:2097152',
            'file' => 'sometimes|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip|max:102400',
            'thumbnail' => 'sometimes|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
            'duration_minutes' => 'sometimes|integer|min:1',
            'difficulty_level' => 'sometimes|in:beginner,intermediate,advanced',
            'learning_outcomes' => 'sometimes|array',
            'prerequisites' => 'sometimes|array',
            'completion_criteria' => 'sometimes|array',
        ]);
        
        // Get files from request
        $videoFile = $request->hasFile('video') ? $request->file('video') : null;
        $documentFile = $request->hasFile('file') ? $request->file('file') : null;
        $thumbnailFile = $request->hasFile('thumbnail') ? $request->file('thumbnail') : null;
        
        // Call service to update the course
        $result = $this->courseService->updateCourse(
            auth()->user(),
            $course,
            $request->only([
                'title', 'description', 'price', 'topic_id', 
                'duration_minutes', 'difficulty_level', 
                'learning_outcomes', 'prerequisites', 'completion_criteria'
            ]),
            $videoFile,
            $documentFile,
            $thumbnailFile
        );
        
        // Return response based on result
        return response()->json([
            'message' => $result['message'],
            'course' => $result['success'] ? $result['course'] : null
        ], $result['code']);
    }

    /**
     * Delete a course.
     */
    public function deleteCourse($id) {
        $course = Course::findOrFail($id);
        
        // Call service to delete the course
        $result = $this->courseService->deleteCourse(auth()->user(), $course);
        
        // Return response based on result
        return response()->json([
            'message' => $result['message']
        ], $result['code']);
    }
}