<?php

namespace App\Services;

use App\Models\CourseSection;
use App\Models\CourseLesson;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CourseLessonService
{
    /**
     * Create a new lesson for a course section
     */
    public function createLesson(User $user, CourseSection $section, array $data, 
                               ?UploadedFile $videoFile = null, ?UploadedFile $documentFile = null,
                               ?UploadedFile $thumbnailFile = null): array
    {
        // Check if user is the course owner
        if ($user->id !== $section->course->user_id) {
            return [
                'success' => false,
                'message' => 'You are not authorized to modify this course',
                'code' => 403
            ];
        }
        
        try {
            DB::beginTransaction();
            
            $lesson = new CourseLesson([
                'section_id' => $section->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'content_type' => $data['content_type'] ?? 'video',
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'order' => $data['order'] ?? null, // Will be auto-assigned if null
                'is_preview' => $data['is_preview'] ?? false
            ]);
            
            // Add quiz data if provided and content type is quiz
            if ($lesson->content_type === 'quiz' && isset($data['quiz_data'])) {
                $lesson->quiz_data = $data['quiz_data'];
            }
            
            // Add assignment data if provided and content type is assignment
            if ($lesson->content_type === 'assignment' && isset($data['assignment_data'])) {
                $lesson->assignment_data = $data['assignment_data'];
            }
            
            // Handle thumbnail upload if provided
            if ($thumbnailFile) {
                $thumbnailFilename = $this->generateFilename($thumbnailFile, 'lesson_thumbnail');
                
                Storage::disk('s3')->put(
                    'lesson_thumbnails/' . $thumbnailFilename, 
                    file_get_contents($thumbnailFile)
                );
                $lesson->thumbnail_url = config('filesystems.disks.s3.url') . '/lesson_thumbnails/' . $thumbnailFilename;
            }
            
            // Handle video upload if provided
            if ($videoFile) {
                $videoFilename = $this->generateFilename($videoFile, 'lesson_video');
                
                Storage::disk('s3')->put(
                    'lesson_videos/' . $videoFilename, 
                    file_get_contents($videoFile)
                );
                $lesson->video_url = config('filesystems.disks.s3.url') . '/lesson_videos/' . $videoFilename;
            }
            
            // Handle file upload if provided
            if ($documentFile) {
                $fileFilename = $this->generateFilename($documentFile, 'lesson_file');
                
                Storage::disk('s3')->put(
                    'lesson_files/' . $fileFilename, 
                    file_get_contents($documentFile)
                );
                $lesson->file_url = config('filesystems.disks.s3.url') . '/lesson_files/' . $fileFilename;
            }
            
            $lesson->save();
            
            // Update course duration if provided
            if (isset($data['duration_minutes']) && $data['duration_minutes'] > 0) {
                $this->updateCourseDuration($section->course_id);
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Lesson created successfully',
                'lesson' => $lesson,
                'code' => 201
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lesson creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Lesson creation failed: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
    
    /**
     * Update an existing lesson
     */
    public function updateLesson(User $user, CourseLesson $lesson, array $data,
                               ?UploadedFile $videoFile = null, ?UploadedFile $documentFile = null,
                               ?UploadedFile $thumbnailFile = null): array
    {
        // Check if user is the course owner
        if ($user->id !== $lesson->section->course->user_id) {
            return [
                'success' => false,
                'message' => 'You are not authorized to modify this course',
                'code' => 403
            ];
        }
        
        try {
            DB::beginTransaction();
            
            // Update basic properties if provided
            if (isset($data['title'])) {
                $lesson->title = $data['title'];
            }
            
            if (isset($data['description'])) {
                $lesson->description = $data['description'];
            }
            
            if (isset($data['content_type'])) {
                $lesson->content_type = $data['content_type'];
            }
            
            if (isset($data['duration_minutes'])) {
                $lesson->duration_minutes = $data['duration_minutes'];
            }
            
            if (isset($data['is_preview'])) {
                $lesson->is_preview = $data['is_preview'];
            }
            
            // Only update order if explicitly provided
            if (isset($data['order'])) {
                // Ensure order is unique within the section
                $this->reorderLessons($lesson->section_id, $lesson->id, $data['order']);
                $lesson->order = $data['order'];
            }
            
            // Update quiz data if provided and content type is quiz
            if ($lesson->content_type === 'quiz' && isset($data['quiz_data'])) {
                $lesson->quiz_data = $data['quiz_data'];
            }
            
            // Update assignment data if provided and content type is assignment
            if ($lesson->content_type === 'assignment' && isset($data['assignment_data'])) {
                $lesson->assignment_data = $data['assignment_data'];
            }
            
            // Handle thumbnail upload if provided
            if ($thumbnailFile) {
                // Delete old thumbnail if it exists
                if ($lesson->thumbnail_url) {
                    $oldPath = str_replace(config('filesystems.disks.s3.url') . '/', '', $lesson->thumbnail_url);
                    Storage::disk('s3')->delete($oldPath);
                }
                
                $thumbnailFilename = $this->generateFilename($thumbnailFile, 'lesson_thumbnail');
                
                Storage::disk('s3')->put(
                    'lesson_thumbnails/' . $thumbnailFilename, 
                    file_get_contents($thumbnailFile)
                );
                $lesson->thumbnail_url = config('filesystems.disks.s3.url') . '/lesson_thumbnails/' . $thumbnailFilename;
            }
            
            // Handle video upload if provided
            if ($videoFile) {
                // Delete old video if it exists
                if ($lesson->video_url) {
                    $oldPath = str_replace(config('filesystems.disks.s3.url') . '/', '', $lesson->video_url);
                    Storage::disk('s3')->delete($oldPath);
                }
                
                $videoFilename = $this->generateFilename($videoFile, 'lesson_video');
                
                Storage::disk('s3')->put(
                    'lesson_videos/' . $videoFilename, 
                    file_get_contents($videoFile)
                );
                $lesson->video_url = config('filesystems.disks.s3.url') . '/lesson_videos/' . $videoFilename;
            }
            
            // Handle file upload if provided
            if ($documentFile) {
                // Delete old file if it exists
                if ($lesson->file_url) {
                    $oldPath = str_replace(config('filesystems.disks.s3.url') . '/', '', $lesson->file_url);
                    Storage::disk('s3')->delete($oldPath);
                }
                
                $fileFilename = $this->generateFilename($documentFile, 'lesson_file');
                
                Storage::disk('s3')->put(
                    'lesson_files/' . $fileFilename, 
                    file_get_contents($documentFile)
                );
                $lesson->file_url = config('filesystems.disks.s3.url') . '/lesson_files/' . $fileFilename;
            }
            
            $lesson->save();
            
            // Update course duration if changed
            if (isset($data['duration_minutes'])) {
                $this->updateCourseDuration($lesson->section->course_id);
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Lesson updated successfully',
                'lesson' => $lesson->fresh(),
                'code' => 200
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lesson update failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Lesson update failed: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
    
    /**
     * Delete a lesson and its files
     */
    public function deleteLesson(User $user, CourseLesson $lesson): array
    {
        // Check if user is the course owner
        if ($user->id !== $lesson->section->course->user_id) {
            return [
                'success' => false,
                'message' => 'You are not authorized to modify this course',
                'code' => 403
            ];
        }
        
        try {
            DB::beginTransaction();
            
            // Delete lesson files if they exist
            if ($lesson->video_url) {
                $videoPath = str_replace(config('filesystems.disks.s3.url') . '/', '', $lesson->video_url);
                Storage::disk('s3')->delete($videoPath);
            }
            
            if ($lesson->file_url) {
                $filePath = str_replace(config('filesystems.disks.s3.url') . '/', '', $lesson->file_url);
                Storage::disk('s3')->delete($filePath);
            }
            
            if ($lesson->thumbnail_url) {
                $thumbnailPath = str_replace(config('filesystems.disks.s3.url') . '/', '', $lesson->thumbnail_url);
                Storage::disk('s3')->delete($thumbnailPath);
            }
            
            // Store course_id and section_id before deleting
            $sectionId = $lesson->section_id;
            $courseId = $lesson->section->course_id;
            
            // Delete the lesson
            $lesson->delete();
            
            // Reorder remaining lessons to ensure no gaps
            $this->normalizeOrdering($sectionId);
            
            // Update course duration
            $this->updateCourseDuration($courseId);
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Lesson deleted successfully',
                'code' => 200
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lesson deletion failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Lesson deletion failed: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
    
    /**
     * Get lesson details with accessibility check
     */
    public function getLesson(CourseLesson $lesson, User $user = null): array
    {
        try {
            $lesson->load('section.course');
            
            // Check if lesson is accessible to user
            $isPreview = $lesson->is_preview;
            $isEnrolled = false;
            
            if ($user) {
                $isEnrolled = $lesson->section->course->isUserEnrolled($user);
                $isOwner = $user->id === $lesson->section->course->user_id;
            } else {
                $isOwner = false;
            }
            
            $isAccessible = $isPreview || $isEnrolled || $isOwner;
            
            // If not accessible, return limited info
            if (!$isAccessible) {
                return [
                    'success' => true,
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
                            'id' => $lesson->section->course->id,
                            'title' => $lesson->section->course->title
                        ],
                        'section' => [
                            'id' => $lesson->section->id,
                            'title' => $lesson->section->title
                        ]
                    ],
                    'code' => 200
                ];
            }
            
            // For accessible lessons, return full details
            return [
                'success' => true,
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
                    'completed' => $isEnrolled ? $lesson->isCompletedBy($user) : false,
                    'course' => [
                        'id' => $lesson->section->course->id,
                        'title' => $lesson->section->course->title
                    ],
                    'section' => [
                        'id' => $lesson->section->id,
                        'title' => $lesson->section->title
                    ],
                    'next_lesson' => $this->getNextLessonId($lesson),
                    'prev_lesson' => $this->getPrevLessonId($lesson)
                ],
                'code' => 200
            ];
            
        } catch (\Exception $e) {
            Log::error('Error fetching lesson: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error fetching lesson: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
    
    /**
     * Reorder lessons to accommodate a new order value
     */
    private function reorderLessons(int $sectionId, int $lessonId, int $newOrder): void
    {
        // Get all lessons for this section except the one being updated
        $lessons = CourseLesson::where('section_id', $sectionId)
            ->where('id', '!=', $lessonId)
            ->orderBy('order')
            ->get();
        
        $order = 0;
        
        // Iterate through lessons and adjust order
        foreach ($lessons as $lesson) {
            // If we've reached the new order position, increment order
            if ($order == $newOrder) {
                $order++;
            }
            
            // Set the lesson's order
            $lesson->order = $order;
            $lesson->save();
            
            $order++;
        }
    }
    
    /**
     * Normalize ordering of lessons after deletion
     */
    private function normalizeOrdering(int $sectionId): void
    {
        $lessons = CourseLesson::where('section_id', $sectionId)
            ->orderBy('order')
            ->get();
        
        $order = 0;
        
        foreach ($lessons as $lesson) {
            $lesson->order = $order;
            $lesson->save();
            $order++;
        }
    }
    
    /**
     * Update course total duration
     */
    private function updateCourseDuration(int $courseId): void
    {
        $totalDuration = CourseLesson::whereHas('section', function ($query) use ($courseId) {
            $query->where('course_id', $courseId);
        })->sum('duration_minutes');
        
        // Update the course duration
        $course = Course::find($courseId);
        if ($course) {
            $course->duration_minutes = $totalDuration;
            $course->save();
        }
    }
    
    /**
     * Get the ID of the next lesson
     */
    private function getNextLessonId(CourseLesson $currentLesson): ?int
    {
        // Try to find next lesson in same section
        $nextLesson = CourseLesson::where('section_id', $currentLesson->section_id)
            ->where('order', '>', $currentLesson->order)
            ->orderBy('order')
            ->first();
            
        if ($nextLesson) {
            return $nextLesson->id;
        }
        
        // If no next lesson in same section, try first lesson of next section
        $nextSection = CourseSection::where('course_id', $currentLesson->section->course_id)
            ->where('order', '>', $currentLesson->section->order)
            ->orderBy('order')
            ->first();
            
        if ($nextSection) {
            $firstLessonOfNextSection = CourseLesson::where('section_id', $nextSection->id)
                ->orderBy('order')
                ->first();
                
            if ($firstLessonOfNextSection) {
                return $firstLessonOfNextSection->id;
            }
        }
        
        // No next lesson available
        return null;
    }
    
    /**
     * Get the ID of the previous lesson
     */
    private function getPrevLessonId(CourseLesson $currentLesson): ?int
    {
        // Try to find previous lesson in same section
        $prevLesson = CourseLesson::where('section_id', $currentLesson->section_id)
            ->where('order', '<', $currentLesson->order)
            ->orderBy('order', 'desc')
            ->first();
            
        if ($prevLesson) {
            return $prevLesson->id;
        }
        
        // If no previous lesson in same section, try last lesson of previous section
        $prevSection = CourseSection::where('course_id', $currentLesson->section->course_id)
            ->where('order', '<', $currentLesson->section->order)
            ->orderBy('order', 'desc')
            ->first();
            
        if ($prevSection) {
            $lastLessonOfPrevSection = CourseLesson::where('section_id', $prevSection->id)
                ->orderBy('order', 'desc')
                ->first();
                
            if ($lastLessonOfPrevSection) {
                return $lastLessonOfPrevSection->id;
            }
        }
        
        // No previous lesson available
        return null;
    }
    
    /**
     * Generate a unique filename for uploaded files
     */
    private function generateFilename(UploadedFile $file, string $prefix): string
    {
        return $prefix . '_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
    }
}