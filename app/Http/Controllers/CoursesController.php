<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class CoursesController extends Controller {
    /**
     * Create a new course with both local and S3 storage options.
     */
    public function createCourse(Request $request) {
        // Check if the user is an educator
        $user = auth()->user();
        if ($user->role != \App\Models\User::ROLE_EDUCATOR) {
            return response()->json([
                'message' => 'Only educators can create courses'
            ], 403);
        }

        // Validate the request
        $this->validate($request, [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'topic_id' => 'required|exists:topics,id',
            'content_type' => 'required|in:video,file,both',
            'video' => 'required_if:content_type,video,both|file|mimes:mp4,mov,avi,webm,mkv|max:2097152', // 2GB max
            'file' => 'required_if:content_type,file,both|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip|max:102400', // 100MB max
        ]);

        // Create the course record
        $course = new Course();
        $course->title = $request->title;
        $course->description = $request->description;
        $course->price = $request->price;
        $course->user_id = $user->id;
        $course->topic_id = $request->topic_id;

        // Handle video upload if provided
        if ($request->hasFile('video')) {
            $video = $request->file('video');
            $videoFilename = 'course_video_' . time() . '_' . uniqid() . '.' . $video->getClientOriginalExtension();
            
            // Store in S3
            Storage::disk('s3')->put('course_videos/' . $videoFilename, file_get_contents($video));
            $course->video_url = config('filesystems.disks.s3.url') . '/course_videos/' . $videoFilename;
        }

        // Handle file upload if provided
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileFilename = 'course_file_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            
            // Store in S3
            Storage::disk('s3')->put('course_files/' . $fileFilename, file_get_contents($file));
            $course->file_url = config('filesystems.disks.s3.url') . '/course_files/' . $fileFilename;
        }

        // Save the course
        $course->save();

        return response()->json([
            'message' => 'Course created successfully',
            'course' => $course
        ], 201);
    }

    /**
     * Display the specified course.
     */
    public function viewCourse($id) {
        $course = Course::with(['user'])->findOrFail($id);
        
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
     * List all courses with optional filtering.
     */
    public function listCourses(Request $request) {
        $query = Course::with(['user']);
        
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
        
        // Order by popularity or newest
        if ($request->has('order_by')) {
            if ($request->order_by === 'popular') {
                $query->withCount('enrollments')->orderBy('enrollments_count', 'desc');
            } elseif ($request->order_by === 'newest') {
                $query->orderBy('created_at', 'desc');
            }
        } else {
            // Default order is newest
            $query->orderBy('created_at', 'desc');
        }
        
        // Paginate the results
        $courses = $query->paginate(12);
        
        return response()->json([
            'courses' => $courses
        ]);
    }

    /**
     * Update an existing course.
     */
    public function updateCourse(Request $request, $id) {
        $course = Course::findOrFail($id);
        
        // Check if the authenticated user is the owner of the course
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
        ]);
        
        // Update the course fields
        if ($request->has('title')) {
            $course->title = $request->title;
        }
        
        if ($request->has('description')) {
            $course->description = $request->description;
        }
        
        if ($request->has('price')) {
            $course->price = $request->price;
        }
        
        if ($request->has('topic_id')) {
            $course->topic_id = $request->topic_id;
        }
        
        // Handle video upload if provided
        if ($request->hasFile('video')) {
            // Delete the old video if it exists
            if ($course->video_url) {
                $oldPath = str_replace(config('filesystems.disks.s3.url') . '/', '', $course->video_url);
                Storage::disk('s3')->delete($oldPath);
            }
            
            $video = $request->file('video');
            $videoFilename = 'course_video_' . time() . '_' . uniqid() . '.' . $video->getClientOriginalExtension();
            
            // Store in S3
            Storage::disk('s3')->put('course_videos/' . $videoFilename, file_get_contents($video));
            $course->video_url = config('filesystems.disks.s3.url') . '/course_videos/' . $videoFilename;
        }
        
        // Handle file upload if provided
        if ($request->hasFile('file')) {
            // Delete the old file if it exists
            if ($course->file_url) {
                $oldPath = str_replace(config('filesystems.disks.s3.url') . '/', '', $course->file_url);
                Storage::disk('s3')->delete($oldPath);
            }
            
            $file = $request->file('file');
            $fileFilename = 'course_file_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            
            // Store in S3
            Storage::disk('s3')->put('course_files/' . $fileFilename, file_get_contents($file));
            $course->file_url = config('filesystems.disks.s3.url') . '/course_files/' . $fileFilename;
        }
        
        // Save the course
        $course->save();
        
        return response()->json([
            'message' => 'Course updated successfully',
            'course' => $course
        ]);
    }

    /**
     * Delete a course.
     */
    public function deleteCourse($id) {
        $course = Course::findOrFail($id);
        
        // Check if the authenticated user is the owner of the course
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
        
        // Delete the associated files
        if ($course->video_url) {
            $videoPath = str_replace(config('filesystems.disks.s3.url') . '/', '', $course->video_url);
            Storage::disk('s3')->delete($videoPath);
        }
        
        if ($course->file_url) {
            $filePath = str_replace(config('filesystems.disks.s3.url') . '/', '', $course->file_url);
            Storage::disk('s3')->delete($filePath);
        }
        
        // Delete the course
        $course->delete();
        
        return response()->json([
            'message' => 'Course deleted successfully'
        ]);
    }
    
    /**
     * Get course content (protected by enrollment check).
     */
    public function getCourseContent($id) {
        $course = Course::findOrFail($id);
        $user = auth()->user();
        
        // Check if the user is enrolled or is the course creator
        if (!$course->isUserEnrolled($user) && $course->user_id !== $user->id) {
            return response()->json([
                'message' => 'You need to enroll in this course to access its content'
            ], 403);
        }
        
        // Return the content URLs
        return response()->json([
            'video_url' => $course->video_url,
            'file_url' => $course->file_url,
            'title' => $course->title,
            'description' => $course->description
        ]);
    }
}