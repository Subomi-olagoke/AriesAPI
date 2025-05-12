<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CourseLesson;
use App\Models\Topic;
use App\Models\User;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EducatorCoursesController extends Controller
{
    /**
     * Display a listing of the educator's courses.
     */
    public function index()
    {
        $user = Auth::user();
        
        // Ensure user is an educator
        if ($user->role != User::ROLE_EDUCATOR) {
            return redirect()->route('home')->with('error', 'You do not have permission to access the educator dashboard.');
        }
        
        // Get all courses by this educator with stats
        $courses = Course::where('user_id', $user->id)
            ->withCount('enrollments')
            ->withCount('lessons')
            ->with('topic')
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        
        // For each course, calculate additional stats
        foreach ($courses as $course) {
            // Total revenue for this course
            $course->total_revenue = $course->enrollments_count * $course->price;
            
            // Completion rate (average progress of enrolled students)
            $enrollments = $course->enrollments;
            
            if ($enrollments->count() > 0) {
                $course->completion_rate = $enrollments->avg('progress');
            } else {
                $course->completion_rate = 0;
            }
        }
        
        return view('educators.courses.index', compact('courses'));
    }

    /**
     * Show the form for creating a new course.
     */
    public function create()
    {
        $user = Auth::user();
        
        // Ensure user is an educator
        if ($user->role != User::ROLE_EDUCATOR) {
            return redirect()->route('home')->with('error', 'You do not have permission to create courses.');
        }
        
        // Get topics for dropdown
        $topics = Topic::orderBy('name')->get();
        
        return view('educators.courses.create', compact('topics'));
    }

    /**
     * Store a newly created course in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        // Ensure user is an educator
        if ($user->role != User::ROLE_EDUCATOR) {
            return redirect()->route('home')->with('error', 'You do not have permission to create courses.');
        }
        
        // Validate request
        $this->validate($request, [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'topic_id' => 'required|exists:topics,id',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:5120', // 5MB max
            'duration_minutes' => 'nullable|integer|min:1',
            'difficulty_level' => 'nullable|in:beginner,intermediate,advanced',
            'learning_outcomes' => 'nullable|array',
            'prerequisites' => 'nullable|array',
            'completion_criteria' => 'nullable|array',
        ]);
        
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
            if ($request->filled('duration_minutes')) {
                $course->duration_minutes = $request->duration_minutes;
            }
            
            if ($request->filled('difficulty_level')) {
                $course->difficulty_level = $request->difficulty_level;
            }
            
            if ($request->has('learning_outcomes')) {
                $course->learning_outcomes = $request->learning_outcomes;
            }
            
            if ($request->has('prerequisites')) {
                $course->prerequisites = $request->prerequisites;
            }
            
            if ($request->has('completion_criteria')) {
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
            
            // Save the course
            $course->save();
            
            DB::commit();
            
            return redirect()->route('educator.courses.show', $course->id)
                ->with('success', 'Course created successfully! Now add sections and lessons.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Course creation failed: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Course creation failed: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified course.
     */
    public function show($id)
    {
        $user = Auth::user();
        $course = Course::with(['sections.lessons', 'topic'])
            ->withCount('enrollments')
            ->findOrFail($id);
        
        // Ensure user is the course owner
        if ($user->id !== $course->user_id) {
            return redirect()->route('educator.courses.index')
                ->with('error', 'You do not have permission to view this course.');
        }
        
        // Calculate course completion rates
        $enrollments = $course->enrollments;
        $completionRate = 0;
        
        if ($enrollments->count() > 0) {
            $completionRate = $enrollments->avg('progress');
        }
        
        // Calculate total revenue for this course
        $totalRevenue = $course->enrollments_count * $course->price;
        
        // Get recent enrollments
        $recentEnrollments = $course->enrollments()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
        
        return view('educators.courses.show', compact('course', 'completionRate', 'totalRevenue', 'recentEnrollments'));
    }

    /**
     * Show the form for editing the specified course.
     */
    public function edit($id)
    {
        $user = Auth::user();
        $course = Course::findOrFail($id);
        
        // Ensure user is the course owner
        if ($user->id !== $course->user_id) {
            return redirect()->route('educator.courses.index')
                ->with('error', 'You do not have permission to edit this course.');
        }
        
        // Get topics for dropdown
        $topics = Topic::orderBy('name')->get();
        
        return view('educators.courses.edit', compact('course', 'topics'));
    }

    /**
     * Update the specified course in storage.
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $course = Course::findOrFail($id);
        
        // Ensure user is the course owner
        if ($user->id !== $course->user_id) {
            return redirect()->route('educator.courses.index')
                ->with('error', 'You do not have permission to edit this course.');
        }
        
        // Validate request
        $this->validate($request, [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'topic_id' => 'required|exists:topics,id',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:5120', // 5MB max
            'duration_minutes' => 'nullable|integer|min:1',
            'difficulty_level' => 'nullable|in:beginner,intermediate,advanced',
            'learning_outcomes' => 'nullable|array',
            'prerequisites' => 'nullable|array',
            'completion_criteria' => 'nullable|array',
        ]);
        
        try {
            DB::beginTransaction();
            
            // Update course
            $course->title = $request->title;
            $course->description = $request->description;
            $course->price = $request->price;
            $course->topic_id = $request->topic_id;
            
            // Update additional properties
            if ($request->filled('duration_minutes')) {
                $course->duration_minutes = $request->duration_minutes;
            }
            
            if ($request->filled('difficulty_level')) {
                $course->difficulty_level = $request->difficulty_level;
            }
            
            if ($request->has('learning_outcomes')) {
                $course->learning_outcomes = $request->learning_outcomes;
            }
            
            if ($request->has('prerequisites')) {
                $course->prerequisites = $request->prerequisites;
            }
            
            if ($request->has('completion_criteria')) {
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
            
            // Save the updated course
            $course->save();
            
            DB::commit();
            
            return redirect()->route('educator.courses.show', $course->id)
                ->with('success', 'Course updated successfully.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Course update failed: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Course update failed: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Toggle the "Featured" status of the course.
     */
    public function toggleFeatured($id)
    {
        $user = Auth::user();
        $course = Course::findOrFail($id);
        
        // Ensure user is the course owner
        if ($user->id !== $course->user_id) {
            return redirect()->route('educator.courses.index')
                ->with('error', 'You do not have permission to feature this course.');
        }
        
        // Toggle featured status
        $course->is_featured = !$course->is_featured;
        $course->save();
        
        $message = $course->is_featured ? 'Course is now featured.' : 'Course is no longer featured.';
        
        return redirect()->back()->with('success', $message);
    }

    /**
     * Remove the specified course from storage.
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $course = Course::findOrFail($id);
        
        // Ensure user is the course owner
        if ($user->id !== $course->user_id) {
            return redirect()->route('educator.courses.index')
                ->with('error', 'You do not have permission to delete this course.');
        }
        
        // Check if anyone is enrolled
        if ($course->enrollments()->count() > 0) {
            return redirect()->route('educator.courses.show', $course->id)
                ->with('error', 'Cannot delete course with active enrollments.');
        }
        
        try {
            DB::beginTransaction();
            
            // Get the file upload service
            $fileUploadService = app(FileUploadService::class);
            
            // Delete associated media files from Cloudinary
            if ($course->thumbnail_url) {
                $fileUploadService->deleteFile($course->thumbnail_url);
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
            
            return redirect()->route('educator.courses.index')
                ->with('success', 'Course deleted successfully.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Course deletion failed: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Course deletion failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Show the form for creating a new section.
     */
    public function createSection($courseId)
    {
        $user = Auth::user();
        $course = Course::findOrFail($courseId);
        
        // Ensure user is the course owner
        if ($user->id !== $course->user_id) {
            return redirect()->route('educator.courses.index')
                ->with('error', 'You do not have permission to modify this course.');
        }
        
        return view('educators.courses.sections.create', compact('course'));
    }
    
    /**
     * Store a newly created section in storage.
     */
    public function storeSection(Request $request, $courseId)
    {
        $user = Auth::user();
        $course = Course::findOrFail($courseId);
        
        // Ensure user is the course owner
        if ($user->id !== $course->user_id) {
            return redirect()->route('educator.courses.index')
                ->with('error', 'You do not have permission to modify this course.');
        }
        
        // Validate request
        $this->validate($request, [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);
        
        // Create new section
        $section = new CourseSection();
        $section->course_id = $course->id;
        $section->title = $request->title;
        $section->description = $request->description;
        
        $section->save();
        
        return redirect()->route('educator.courses.show', $course->id)
            ->with('success', 'Section created successfully. Now add lessons to this section.');
    }
    
    /**
     * Show the form for editing a section.
     */
    public function editSection($courseId, $sectionId)
    {
        $user = Auth::user();
        $course = Course::findOrFail($courseId);
        
        // Ensure user is the course owner
        if ($user->id !== $course->user_id) {
            return redirect()->route('educator.courses.index')
                ->with('error', 'You do not have permission to modify this course.');
        }
        
        $section = CourseSection::where('course_id', $courseId)
            ->where('id', $sectionId)
            ->firstOrFail();
        
        return view('educators.courses.sections.edit', compact('course', 'section'));
    }
    
    /**
     * Update the specified section in storage.
     */
    public function updateSection(Request $request, $courseId, $sectionId)
    {
        $user = Auth::user();
        $course = Course::findOrFail($courseId);
        
        // Ensure user is the course owner
        if ($user->id !== $course->user_id) {
            return redirect()->route('educator.courses.index')
                ->with('error', 'You do not have permission to modify this course.');
        }
        
        $section = CourseSection::where('course_id', $courseId)
            ->where('id', $sectionId)
            ->firstOrFail();
        
        // Validate request
        $this->validate($request, [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);
        
        // Update section
        $section->title = $request->title;
        $section->description = $request->description;
        
        $section->save();
        
        return redirect()->route('educator.courses.show', $course->id)
            ->with('success', 'Section updated successfully.');
    }
    
    /**
     * Remove the specified section from storage.
     */
    public function destroySection($courseId, $sectionId)
    {
        $user = Auth::user();
        $course = Course::findOrFail($courseId);
        
        // Ensure user is the course owner
        if ($user->id !== $course->user_id) {
            return redirect()->route('educator.courses.index')
                ->with('error', 'You do not have permission to modify this course.');
        }
        
        $section = CourseSection::where('course_id', $courseId)
            ->where('id', $sectionId)
            ->firstOrFail();
        
        try {
            DB::beginTransaction();
            
            // Delete all lessons in this section first
            foreach ($section->lessons as $lesson) {
                // Delete lesson media files
                $fileUploadService = app(FileUploadService::class);
                
                if ($lesson->video_url) {
                    $fileUploadService->deleteFile($lesson->video_url);
                }
                
                if ($lesson->file_url) {
                    $fileUploadService->deleteFile($lesson->file_url);
                }
                
                if ($lesson->thumbnail_url) {
                    $fileUploadService->deleteFile($lesson->thumbnail_url);
                }
                
                // Delete the lesson
                $lesson->delete();
            }
            
            // Delete the section
            $section->delete();
            
            DB::commit();
            
            return redirect()->route('educator.courses.show', $course->id)
                ->with('success', 'Section and all its lessons deleted successfully.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Section deletion failed: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Section deletion failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Show the form for creating a new lesson.
     */
    public function createLesson($courseId, $sectionId)
    {
        $user = Auth::user();
        $course = Course::findOrFail($courseId);
        
        // Ensure user is the course owner
        if ($user->id !== $course->user_id) {
            return redirect()->route('educator.courses.index')
                ->with('error', 'You do not have permission to modify this course.');
        }
        
        $section = CourseSection::where('course_id', $courseId)
            ->where('id', $sectionId)
            ->firstOrFail();
        
        return view('educators.courses.lessons.create', compact('course', 'section'));
    }
    
    /**
     * Store a newly created lesson in storage.
     */
    public function storeLesson(Request $request, $courseId, $sectionId)
    {
        $user = Auth::user();
        $course = Course::findOrFail($courseId);
        
        // Ensure user is the course owner
        if ($user->id !== $course->user_id) {
            return redirect()->route('educator.courses.index')
                ->with('error', 'You do not have permission to modify this course.');
        }
        
        $section = CourseSection::where('course_id', $courseId)
            ->where('id', $sectionId)
            ->firstOrFail();
        
        // Validate request based on content type
        $this->validate($request, [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content_type' => 'required|in:video,document,quiz,assignment',
            'duration_minutes' => 'nullable|integer|min:1',
            'is_preview' => 'nullable|boolean',
            'video' => 'required_if:content_type,video|file|mimes:mp4,mov,avi,webm,mkv|max:5242880', // 5GB max
            'file' => 'required_if:content_type,document|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip|max:204800', // 200MB max
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:10240', // 10MB max
        ]);
        
        try {
            DB::beginTransaction();
            
            // Create new lesson
            $lesson = new CourseLesson();
            $lesson->section_id = $section->id;
            $lesson->title = $request->title;
            $lesson->description = $request->description;
            $lesson->content_type = $request->content_type;
            $lesson->duration_minutes = $request->duration_minutes;
            $lesson->is_preview = $request->has('is_preview');
            
            // Use Cloudinary for file uploads
            $fileUploadService = app(FileUploadService::class);
            
            // Handle video upload
            if ($request->hasFile('video')) {
                $lesson->video_url = $fileUploadService->uploadFile(
                    $request->file('video'),
                    'lesson_videos'
                );
                
                if (!$lesson->video_url) {
                    throw new \Exception('Failed to upload video to Cloudinary');
                }
            }
            
            // Handle document upload
            if ($request->hasFile('file')) {
                $lesson->file_url = $fileUploadService->uploadFile(
                    $request->file('file'),
                    'lesson_files'
                );
                
                if (!$lesson->file_url) {
                    throw new \Exception('Failed to upload file to Cloudinary');
                }
            }
            
            // Handle thumbnail upload
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
                
                if (!$lesson->thumbnail_url) {
                    throw new \Exception('Failed to upload thumbnail to Cloudinary');
                }
            }
            
            // Save the lesson
            $lesson->save();
            
            // Update course total duration if lesson duration is provided
            if ($request->filled('duration_minutes')) {
                $totalDuration = CourseLesson::whereHas('section', function ($query) use ($courseId) {
                    $query->where('course_id', $courseId);
                })->sum('duration_minutes');
                
                $course->duration_minutes = $totalDuration;
                $course->save();
            }
            
            DB::commit();
            
            return redirect()->route('educator.courses.show', $course->id)
                ->with('success', 'Lesson created successfully.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lesson creation failed: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Lesson creation failed: ' . $e->getMessage())
                ->withInput();
        }
    }
    
    /**
     * Show the form for editing a lesson.
     */
    public function editLesson($courseId, $lessonId)
    {
        $user = Auth::user();
        $course = Course::findOrFail($courseId);
        
        // Ensure user is the course owner
        if ($user->id !== $course->user_id) {
            return redirect()->route('educator.courses.index')
                ->with('error', 'You do not have permission to modify this course.');
        }
        
        $lesson = CourseLesson::whereHas('section', function ($query) use ($courseId) {
            $query->where('course_id', $courseId);
        })->findOrFail($lessonId);
        
        $section = $lesson->section;
        
        return view('educators.courses.lessons.edit', compact('course', 'section', 'lesson'));
    }
    
    /**
     * Update the specified lesson in storage.
     */
    public function updateLesson(Request $request, $courseId, $lessonId)
    {
        $user = Auth::user();
        $course = Course::findOrFail($courseId);
        
        // Ensure user is the course owner
        if ($user->id !== $course->user_id) {
            return redirect()->route('educator.courses.index')
                ->with('error', 'You do not have permission to modify this course.');
        }
        
        $lesson = CourseLesson::whereHas('section', function ($query) use ($courseId) {
            $query->where('course_id', $courseId);
        })->findOrFail($lessonId);
        
        // Validate request based on content type
        $this->validate($request, [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content_type' => 'required|in:video,document,quiz,assignment',
            'duration_minutes' => 'nullable|integer|min:1',
            'is_preview' => 'nullable|boolean',
            'video' => 'nullable|file|mimes:mp4,mov,avi,webm,mkv|max:5242880', // 5GB max
            'file' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip|max:204800', // 200MB max
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:10240', // 10MB max
        ]);
        
        try {
            DB::beginTransaction();
            
            // Update lesson
            $lesson->title = $request->title;
            $lesson->description = $request->description;
            $lesson->content_type = $request->content_type;
            
            if ($request->filled('duration_minutes')) {
                $lesson->duration_minutes = $request->duration_minutes;
            }
            
            $lesson->is_preview = $request->has('is_preview');
            
            // Use Cloudinary for file uploads
            $fileUploadService = app(FileUploadService::class);
            
            // Handle video upload
            if ($request->hasFile('video')) {
                // Delete old video if it exists
                if ($lesson->video_url) {
                    $fileUploadService->deleteFile($lesson->video_url);
                }
                
                $lesson->video_url = $fileUploadService->uploadFile(
                    $request->file('video'),
                    'lesson_videos'
                );
                
                if (!$lesson->video_url) {
                    throw new \Exception('Failed to upload video to Cloudinary');
                }
            }
            
            // Handle document upload
            if ($request->hasFile('file')) {
                // Delete old file if it exists
                if ($lesson->file_url) {
                    $fileUploadService->deleteFile($lesson->file_url);
                }
                
                $lesson->file_url = $fileUploadService->uploadFile(
                    $request->file('file'),
                    'lesson_files'
                );
                
                if (!$lesson->file_url) {
                    throw new \Exception('Failed to upload file to Cloudinary');
                }
            }
            
            // Handle thumbnail upload
            if ($request->hasFile('thumbnail')) {
                // Delete old thumbnail if it exists
                if ($lesson->thumbnail_url) {
                    $fileUploadService->deleteFile($lesson->thumbnail_url);
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
                
                if (!$lesson->thumbnail_url) {
                    throw new \Exception('Failed to upload thumbnail to Cloudinary');
                }
            }
            
            // Save the lesson
            $lesson->save();
            
            // Update course total duration
            $totalDuration = CourseLesson::whereHas('section', function ($query) use ($courseId) {
                $query->where('course_id', $courseId);
            })->sum('duration_minutes');
            
            $course->duration_minutes = $totalDuration;
            $course->save();
            
            DB::commit();
            
            return redirect()->route('educator.courses.show', $course->id)
                ->with('success', 'Lesson updated successfully.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lesson update failed: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Lesson update failed: ' . $e->getMessage())
                ->withInput();
        }
    }
    
    /**
     * Remove the specified lesson from storage.
     */
    public function destroyLesson($courseId, $lessonId)
    {
        $user = Auth::user();
        $course = Course::findOrFail($courseId);
        
        // Ensure user is the course owner
        if ($user->id !== $course->user_id) {
            return redirect()->route('educator.courses.index')
                ->with('error', 'You do not have permission to modify this course.');
        }
        
        $lesson = CourseLesson::whereHas('section', function ($query) use ($courseId) {
            $query->where('course_id', $courseId);
        })->findOrFail($lessonId);
        
        try {
            DB::beginTransaction();
            
            // Delete lesson media files
            $fileUploadService = app(FileUploadService::class);
            
            if ($lesson->video_url) {
                $fileUploadService->deleteFile($lesson->video_url);
            }
            
            if ($lesson->file_url) {
                $fileUploadService->deleteFile($lesson->file_url);
            }
            
            if ($lesson->thumbnail_url) {
                $fileUploadService->deleteFile($lesson->thumbnail_url);
            }
            
            // Record lesson duration for updating course total duration
            $lessonDuration = $lesson->duration_minutes;
            
            // Delete the lesson
            $lesson->delete();
            
            // Update course total duration
            if ($lessonDuration > 0) {
                $totalDuration = CourseLesson::whereHas('section', function ($query) use ($courseId) {
                    $query->where('course_id', $courseId);
                })->sum('duration_minutes');
                
                $course->duration_minutes = $totalDuration;
                $course->save();
            }
            
            DB::commit();
            
            return redirect()->route('educator.courses.show', $course->id)
                ->with('success', 'Lesson deleted successfully.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lesson deletion failed: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Lesson deletion failed: ' . $e->getMessage());
        }
    }
}