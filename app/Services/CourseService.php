<?php

namespace App\Services;

use App\Models\Course;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CourseService
{
    /**
     * Create a new course with enhanced properties
     * 
     * @param User $user The educator creating the course
     * @param array $data Course data from the request
     * @param UploadedFile|null $videoFile Video file if provided
     * @param UploadedFile|null $documentFile Document file if provided
     * @param UploadedFile|null $thumbnailFile Thumbnail image if provided
     * @return array Response with success/error status and course or message
     */
    public function createCourse(User $user, array $data, ?UploadedFile $videoFile = null, 
                               ?UploadedFile $documentFile = null, ?UploadedFile $thumbnailFile = null): array
    {
        // Check if the user is an educator
        if ($user->role != User::ROLE_EDUCATOR) {
            return [
                'success' => false,
                'message' => 'Only educators can create courses',
                'code' => 403
            ];
        }
        
        try {
            DB::beginTransaction();
            
            // Create the course record
            $course = new Course();
            $course->title = $data['title'];
            $course->description = $data['description'];
            $course->price = $data['price'];
            $course->user_id = $user->id;
            $course->topic_id = $data['topic_id'];
            
            // Add new properties if provided
            if (isset($data['duration_minutes'])) {
                $course->duration_minutes = $data['duration_minutes'];
            }
            
            if (isset($data['difficulty_level'])) {
                $course->difficulty_level = $data['difficulty_level'];
            }
            
            if (isset($data['learning_outcomes'])) {
                $course->learning_outcomes = $data['learning_outcomes'];
            }
            
            if (isset($data['prerequisites'])) {
                $course->prerequisites = $data['prerequisites'];
            }
            
            if (isset($data['completion_criteria'])) {
                $course->completion_criteria = $data['completion_criteria'];
            }
            
            // Handle thumbnail upload if provided
            if ($thumbnailFile) {
                $thumbnailFilename = $this->generateFilename($thumbnailFile, 'course_thumbnail');
                
                try {
                    Storage::disk('s3')->put(
                        'course_thumbnails/' . $thumbnailFilename, 
                        file_get_contents($thumbnailFile)
                    );
                    $course->thumbnail_url = config('filesystems.disks.s3.url') . '/course_thumbnails/' . $thumbnailFilename;
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Thumbnail upload failed: ' . $e->getMessage());
                    return [
                        'success' => false,
                        'message' => 'Failed to upload thumbnail: ' . $e->getMessage(),
                        'code' => 500
                    ];
                }
            }
            
            // Handle video upload if provided
            if ($videoFile) {
                $videoFilename = $this->generateFilename($videoFile, 'course_video');
                
                try {
                    Storage::disk('s3')->put(
                        'course_videos/' . $videoFilename, 
                        file_get_contents($videoFile)
                    );
                    $course->video_url = config('filesystems.disks.s3.url') . '/course_videos/' . $videoFilename;
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Video upload failed: ' . $e->getMessage());
                    return [
                        'success' => false,
                        'message' => 'Failed to upload video: ' . $e->getMessage(),
                        'code' => 500
                    ];
                }
            }
            
            // Handle file upload if provided
            if ($documentFile) {
                $fileFilename = $this->generateFilename($documentFile, 'course_file');
                
                try {
                    Storage::disk('s3')->put(
                        'course_files/' . $fileFilename, 
                        file_get_contents($documentFile)
                    );
                    $course->file_url = config('filesystems.disks.s3.url') . '/course_files/' . $fileFilename;
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Document upload failed: ' . $e->getMessage());
                    return [
                        'success' => false,
                        'message' => 'Failed to upload document: ' . $e->getMessage(),
                        'code' => 500
                    ];
                }
            }
            
            $course->save();
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Course created successfully',
                'course' => $course,
                'code' => 201
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Course creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Course creation failed: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
    
    /**
     * Update an existing course
     */
    public function updateCourse(User $user, Course $course, array $data, ?UploadedFile $videoFile = null,
                               ?UploadedFile $documentFile = null, ?UploadedFile $thumbnailFile = null): array
    {
        // Check if user is the course owner
        if ($user->id !== $course->user_id) {
            return [
                'success' => false,
                'message' => 'You are not authorized to update this course',
                'code' => 403
            ];
        }
        
        try {
            DB::beginTransaction();
            
            // Update basic properties if provided
            if (isset($data['title'])) {
                $course->title = $data['title'];
            }
            
            if (isset($data['description'])) {
                $course->description = $data['description'];
            }
            
            if (isset($data['price'])) {
                $course->price = $data['price'];
            }
            
            if (isset($data['topic_id'])) {
                $course->topic_id = $data['topic_id'];
            }
            
            // Update new properties if provided
            if (isset($data['duration_minutes'])) {
                $course->duration_minutes = $data['duration_minutes'];
            }
            
            if (isset($data['difficulty_level'])) {
                $course->difficulty_level = $data['difficulty_level'];
            }
            
            if (isset($data['learning_outcomes'])) {
                $course->learning_outcomes = $data['learning_outcomes'];
            }
            
            if (isset($data['prerequisites'])) {
                $course->prerequisites = $data['prerequisites'];
            }
            
            if (isset($data['completion_criteria'])) {
                $course->completion_criteria = $data['completion_criteria'];
            }
            
            // Handle thumbnail upload if provided
            if ($thumbnailFile) {
                // Delete old thumbnail if it exists
                if ($course->thumbnail_url) {
                    $oldPath = str_replace(config('filesystems.disks.s3.url') . '/', '', $course->thumbnail_url);
                    Storage::disk('s3')->delete($oldPath);
                }
                
                $thumbnailFilename = $this->generateFilename($thumbnailFile, 'course_thumbnail');
                
                Storage::disk('s3')->put(
                    'course_thumbnails/' . $thumbnailFilename, 
                    file_get_contents($thumbnailFile)
                );
                $course->thumbnail_url = config('filesystems.disks.s3.url') . '/course_thumbnails/' . $thumbnailFilename;
            }
            
            // Handle video upload if provided
            if ($videoFile) {
                // Delete old video if it exists
                if ($course->video_url) {
                    $oldPath = str_replace(config('filesystems.disks.s3.url') . '/', '', $course->video_url);
                    Storage::disk('s3')->delete($oldPath);
                }
                
                $videoFilename = $this->generateFilename($videoFile, 'course_video');
                
                Storage::disk('s3')->put(
                    'course_videos/' . $videoFilename, 
                    file_get_contents($videoFile)
                );
                $course->video_url = config('filesystems.disks.s3.url') . '/course_videos/' . $videoFilename;
            }
            
            // Handle file upload if provided
            if ($documentFile) {
                // Delete old file if it exists
                if ($course->file_url) {
                    $oldPath = str_replace(config('filesystems.disks.s3.url') . '/', '', $course->file_url);
                    Storage::disk('s3')->delete($oldPath);
                }
                
                $fileFilename = $this->generateFilename($documentFile, 'course_file');
                
                Storage::disk('s3')->put(
                    'course_files/' . $fileFilename, 
                    file_get_contents($documentFile)
                );
                $course->file_url = config('filesystems.disks.s3.url') . '/course_files/' . $fileFilename;
            }
            
            $course->save();
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Course updated successfully',
                'course' => $course,
                'code' => 200
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Course update failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Course update failed: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
    
    /**
     * Delete a course and its associated files
     */
    public function deleteCourse(User $user, Course $course): array
    {
        // Check if user owns the course
        if ($user->id !== $course->user_id) {
            return [
                'success' => false,
                'message' => 'You are not authorized to delete this course',
                'code' => 403
            ];
        }
        
        // Check if anyone is enrolled
        if ($course->enrollments()->count() > 0) {
            return [
                'success' => false,
                'message' => 'Cannot delete course with active enrollments',
                'code' => 400
            ];
        }
        
        try {
            DB::beginTransaction();
            
            // Delete associated files
            if ($course->thumbnail_url) {
                $thumbnailPath = str_replace(config('filesystems.disks.s3.url') . '/', '', $course->thumbnail_url);
                Storage::disk('s3')->delete($thumbnailPath);
            }
            
            if ($course->video_url) {
                $videoPath = str_replace(config('filesystems.disks.s3.url') . '/', '', $course->video_url);
                Storage::disk('s3')->delete($videoPath);
            }
            
            if ($course->file_url) {
                $filePath = str_replace(config('filesystems.disks.s3.url') . '/', '', $course->file_url);
                Storage::disk('s3')->delete($filePath);
            }
            
            // Delete all sections and lessons (cascades automatically thanks to foreign keys)
            $course->delete();
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Course deleted successfully',
                'code' => 200
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Course deletion failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Course deletion failed: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
    
    /**
     * Get a course with all its sections and lessons
     */
    public function getCourseWithContent(Course $course, User $user = null): array
    {
        try {
            $course->load(['sections.lessons', 'user', 'topic']);
            
            $data = [
                'course' => $course,
                'sections' => $course->sections,
                'total_duration' => $course->total_duration,
                'lesson_count' => $course->lesson_count,
            ];
            
            // Add enrollment information if user is provided
            if ($user) {
                $data['is_enrolled'] = $course->isUserEnrolled($user);
                $data['enrollment'] = $user->enrollments()
                    ->where('course_id', $course->id)
                    ->first();
                
                // Mark previews and enrollment-accessible content
                foreach ($course->sections as $section) {
                    foreach ($section->lessons as $lesson) {
                        $lesson->accessible = $lesson->is_preview || $data['is_enrolled'];
                        $lesson->completed = $data['is_enrolled'] ? $lesson->isCompletedBy($user) : false;
                    }
                }
            } else {
                // If no user, only mark previews as accessible
                foreach ($course->sections as $section) {
                    foreach ($section->lessons as $lesson) {
                        $lesson->accessible = $lesson->is_preview;
                        $lesson->completed = false;
                    }
                }
            }
            
            return [
                'success' => true,
                'data' => $data,
                'code' => 200
            ];
            
        } catch (\Exception $e) {
            Log::error('Error fetching course content: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error fetching course content: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
    
    /**
     * Generate a unique filename for uploaded files
     */
    private function generateFilename(UploadedFile $file, string $prefix): string
    {
        return $prefix . '_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
    }
}