<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CourseSectionService
{
    /**
     * Create a new course section
     */
    public function createSection(User $user, Course $course, array $data): array
    {
        // Check if user is the course owner
        if ($user->id !== $course->user_id) {
            return [
                'success' => false,
                'message' => 'You are not authorized to modify this course',
                'code' => 403
            ];
        }
        
        try {
            DB::beginTransaction();
            
            $section = new CourseSection([
                'course_id' => $course->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'order' => $data['order'] ?? null // Will be auto-assigned if null
            ]);
            
            $section->save();
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Section created successfully',
                'section' => $section,
                'code' => 201
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Section creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Section creation failed: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
    
    /**
     * Update an existing course section
     */
    public function updateSection(User $user, CourseSection $section, array $data): array
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
            
            if (isset($data['title'])) {
                $section->title = $data['title'];
            }
            
            if (isset($data['description'])) {
                $section->description = $data['description'];
            }
            
            // Only update order if explicitly provided
            if (isset($data['order'])) {
                // Ensure order is unique within the course
                $this->reorderSections($section->course_id, $section->id, $data['order']);
                $section->order = $data['order'];
            }
            
            $section->save();
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Section updated successfully',
                'section' => $section->fresh(),
                'code' => 200
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Section update failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Section update failed: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
    
    /**
     * Delete a course section and its lessons
     */
    public function deleteSection(User $user, CourseSection $section): array
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
            
            // Delete all lessons in this section (and their files)
            foreach ($section->lessons as $lesson) {
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
            }
            
            // Delete the section (this will cascade delete lessons too)
            $section->delete();
            
            // Reorder remaining sections to ensure no gaps
            $this->normalizeOrdering($section->course_id);
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Section deleted successfully',
                'code' => 200
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Section deletion failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Section deletion failed: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
    
    /**
     * Reorder sections to accommodate a new order value
     */
    private function reorderSections(int $courseId, int $sectionId, int $newOrder): void
    {
        // Get all sections for this course except the one being updated
        $sections = CourseSection::where('course_id', $courseId)
            ->where('id', '!=', $sectionId)
            ->orderBy('order')
            ->get();
        
        $order = 0;
        
        // Iterate through sections and adjust order
        foreach ($sections as $section) {
            // If we've reached the new order position, increment order
            if ($order == $newOrder) {
                $order++;
            }
            
            // Set the section's order
            $section->order = $order;
            $section->save();
            
            $order++;
        }
    }
    
    /**
     * Normalize ordering of sections after deletion
     */
    private function normalizeOrdering(int $courseId): void
    {
        $sections = CourseSection::where('course_id', $courseId)
            ->orderBy('order')
            ->get();
        
        $order = 0;
        
        foreach ($sections as $section) {
            $section->order = $order;
            $section->save();
            $order++;
        }
    }
}