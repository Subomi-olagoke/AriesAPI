<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use App\Models\Topic;
use App\Services\CourseService;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CoursesController extends Controller {
    
    protected $courseService;
    
    public function __construct(CourseService $courseService)
    {
        $this->courseService = $courseService;
    }
    
    /**
     * Create a new course with both local and S3 storage options.
     */
    /**
     * Create a new course with Cloudinary for media management.
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

        // Get user
        $user = auth()->user();
        
        // Check if the user is an educator
        if ($user->role != User::ROLE_EDUCATOR) {
            return response()->json([
                'success' => false,
                'message' => 'Only educators can create courses',
                'code' => 403
            ], 403);
        }
        
        try {
            DB::beginTransaction();
            
            // Create new course
            $course = new Course();
            $course->title = $request->title;
            $course->description = $request->description;
            $course->price = $request->price;
            $course->user_id = $user->id;
            $course->topic_id = $request->topic_id;
            
            // Add additional properties
            if (isset($request->duration_minutes)) {
                $course->duration_minutes = $request->duration_minutes;
            }
            
            if (isset($request->difficulty_level)) {
                $course->difficulty_level = $request->difficulty_level;
            }
            
            if (isset($request->learning_outcomes)) {
                $course->learning_outcomes = $request->learning_outcomes;
            }
            
            if (isset($request->prerequisites)) {
                $course->prerequisites = $request->prerequisites;
            }
            
            if (isset($request->completion_criteria)) {
                $course->completion_criteria = $request->completion_criteria;
            }
            
            // Use Cloudinary file upload service for media
            $fileUploadService = app(FileUploadService::class);
            
            // Handle thumbnail upload
            if ($request->hasFile('thumbnail')) {
                $course->thumbnail_url = $fileUploadService->uploadFile(
                    $request->file('thumbnail'),
                    'course_thumbnails',
                    [
                        'process_image' => true,
                        'width' => 800,
                        'height' => 450,
                        'fit' => true
                    ]
                );
                
                if (!$course->thumbnail_url) {
                    throw new \Exception('Failed to upload thumbnail to Cloudinary');
                }
            }
            
            // Handle video upload
            if ($request->hasFile('video')) {
                $course->video_url = $fileUploadService->uploadFile(
                    $request->file('video'),
                    'course_videos'
                );
                
                if (!$course->video_url) {
                    throw new \Exception('Failed to upload video to Cloudinary');
                }
            }
            
            // Handle file upload
            if ($request->hasFile('file')) {
                $course->file_url = $fileUploadService->uploadFile(
                    $request->file('file'),
                    'course_files'
                );
                
                if (!$course->file_url) {
                    throw new \Exception('Failed to upload file to Cloudinary');
                }
            }
            
            // Save the course
            $course->save();
            
            DB::commit();
            
            return response()->json([
                'message' => 'Course created successfully',
                'course' => $course
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Course creation failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Course creation failed: ' . $e->getMessage()
            ], 500);
        }
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
    /**
     * Update an existing course with Cloudinary for media management.
     */
    public function updateCourse(Request $request, $id) {
        $course = Course::findOrFail($id);
        
        // Check if user is the course owner
        if (auth()->id() !== $course->user_id) {
            return response()->json([
                'message' => 'You are not authorized to update this course'
            ], 403);
        }
        
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
        
        try {
            DB::beginTransaction();
            
            // Update basic properties if provided
            if (isset($request->title)) {
                $course->title = $request->title;
            }
            
            if (isset($request->description)) {
                $course->description = $request->description;
            }
            
            if (isset($request->price)) {
                $course->price = $request->price;
            }
            
            if (isset($request->topic_id)) {
                $course->topic_id = $request->topic_id;
            }
            
            // Update additional properties if provided
            if (isset($request->duration_minutes)) {
                $course->duration_minutes = $request->duration_minutes;
            }
            
            if (isset($request->difficulty_level)) {
                $course->difficulty_level = $request->difficulty_level;
            }
            
            if (isset($request->learning_outcomes)) {
                $course->learning_outcomes = $request->learning_outcomes;
            }
            
            if (isset($request->prerequisites)) {
                $course->prerequisites = $request->prerequisites;
            }
            
            if (isset($request->completion_criteria)) {
                $course->completion_criteria = $request->completion_criteria;
            }
            
            // Use Cloudinary file upload service for media updates
            $fileUploadService = app(FileUploadService::class);
            
            // Handle thumbnail update if provided
            if ($request->hasFile('thumbnail')) {
                // Delete old thumbnail if it exists
                if ($course->thumbnail_url) {
                    $fileUploadService->deleteFile($course->thumbnail_url);
                }
                
                $course->thumbnail_url = $fileUploadService->uploadFile(
                    $request->file('thumbnail'),
                    'course_thumbnails',
                    [
                        'process_image' => true,
                        'width' => 800,
                        'height' => 450,
                        'fit' => true
                    ]
                );
                
                if (!$course->thumbnail_url) {
                    throw new \Exception('Failed to upload thumbnail to Cloudinary');
                }
            }
            
            // Handle video update if provided
            if ($request->hasFile('video')) {
                // Delete old video if it exists
                if ($course->video_url) {
                    $fileUploadService->deleteFile($course->video_url);
                }
                
                $course->video_url = $fileUploadService->uploadFile(
                    $request->file('video'),
                    'course_videos'
                );
                
                if (!$course->video_url) {
                    throw new \Exception('Failed to upload video to Cloudinary');
                }
            }
            
            // Handle file update if provided
            if ($request->hasFile('file')) {
                // Delete old file if it exists
                if ($course->file_url) {
                    $fileUploadService->deleteFile($course->file_url);
                }
                
                $course->file_url = $fileUploadService->uploadFile(
                    $request->file('file'),
                    'course_files'
                );
                
                if (!$course->file_url) {
                    throw new \Exception('Failed to upload file to Cloudinary');
                }
            }
            
            // Save the updated course
            $course->save();
            
            DB::commit();
            
            return response()->json([
                'message' => 'Course updated successfully',
                'course' => $course->fresh()
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Course update failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Course update failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a course.
     */
    /**
     * Delete a course and all associated media from Cloudinary.
     */
    public function deleteCourse($id) {
        $course = Course::findOrFail($id);
        
        // Check if user is the course owner
        if (auth()->id() !== $course->user_id) {
            return response()->json([
                'message' => 'You are not authorized to delete this course'
            ], 403);
        }
        
        // Check if anyone is enrolled
        if ($course->enrollments()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete course with active enrollments'
            ], 400);
        }
        
        try {
            DB::beginTransaction();
            
            // Get the file upload service
            $fileUploadService = app(FileUploadService::class);
            
            // Delete associated media files from Cloudinary
            if ($course->thumbnail_url) {
                $fileUploadService->deleteFile($course->thumbnail_url);
            }
            
            if ($course->video_url) {
                $fileUploadService->deleteFile($course->video_url);
            }
            
            if ($course->file_url) {
                $fileUploadService->deleteFile($course->file_url);
            }
            
            // Find all course sections and lessons to delete their files too
            $sections = $course->sections()->with('lessons')->get();
            
            foreach ($sections as $section) {
                foreach ($section->lessons as $lesson) {
                    if ($lesson->video_url) {
                        $fileUploadService->deleteFile($lesson->video_url);
                    }
                    
                    if ($lesson->file_url) {
                        $fileUploadService->deleteFile($lesson->file_url);
                    }
                    
                    if ($lesson->thumbnail_url) {
                        $fileUploadService->deleteFile($lesson->thumbnail_url);
                    }
                }
            }
            
            // Delete the course (this will cascade delete sections and lessons via foreign keys)
            $course->delete();
            
            DB::commit();
            
            return response()->json([
                'message' => 'Course deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Course deletion failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Course deletion failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get courses organized by the user's selected topics.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCoursesByTopic(Request $request)
    {
        $user = auth()->user();
        
        // Get user's selected topics
        $userTopics = $user->topic()->with('courses.user')->get();
        
        $coursesByTopic = [];
        
        // For each topic, get the related courses
        foreach ($userTopics as $topic) {
            $courses = $topic->courses()
                ->with(['user:id,username,first_name,last_name,avatar', 'topic'])
                ->orderBy('created_at', 'desc')
                ->get();
                
            // Add enrollment information for each course
            foreach ($courses as $course) {
                $course->is_enrolled = $course->isUserEnrolled($user);
                $course->enrollment = $user->enrollments()
                    ->where('course_id', $course->id)
                    ->first();
            }
            
            // Only include the topic if it has courses
            if ($courses->count() > 0) {
                $coursesByTopic[] = [
                    'topic' => [
                        'id' => $topic->id,
                        'name' => $topic->name
                    ],
                    'courses' => $courses
                ];
            }
        }
        
        // Optional: Include recommended courses from other topics
        $recommendedCourses = $this->getRecommendedCourses($user, 5); // Get 5 recommended courses
        
        return response()->json([
            'courses_by_topic' => $coursesByTopic,
            'recommended_courses' => $recommendedCourses
        ]);
    }

    /**
     * Get recommended courses for user that aren't in their selected topics.
     * 
     * @param  \App\Models\User  $user
     * @param  int  $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function getRecommendedCourses($user, $limit = 5)
    {
        // Get IDs of user's selected topics
        $userTopicIds = $user->topic()->pluck('topic_id');
        
        try {
            // Try the original approach with enrollments count
            $recommendedCourses = Course::whereNotIn('topic_id', $userTopicIds)
                ->with(['user:id,username,first_name,last_name,avatar', 'topic'])
                ->withCount('enrollments')
                ->orderBy('enrollments_count', 'desc')
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {
            // Fallback if the course_enrollments table doesn't exist
            $recommendedCourses = Course::whereNotIn('topic_id', $userTopicIds)
                ->with(['user:id,username,first_name,last_name,avatar', 'topic'])
                ->inRandomOrder()
                ->limit($limit)
                ->get();
        }
                
        // Add enrollment information
        foreach ($recommendedCourses as $course) {
            try {
                $course->is_enrolled = $course->isUserEnrolled($user);
                $course->enrollment = $user->enrollments()
                    ->where('course_id', $course->id)
                    ->first();
            } catch (\Exception $e) {
                // Fallback if enrollments fail
                $course->is_enrolled = false;
                $course->enrollment = null;
            }
        }
        
        return $recommendedCourses;
    }
}