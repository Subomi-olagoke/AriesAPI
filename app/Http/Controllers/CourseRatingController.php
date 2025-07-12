<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseRating;
use App\Models\CourseEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CourseRatingController extends Controller
{
    /**
     * Rate a course
     */
    public function rateCourse(Request $request, $courseId)
    {
        $user = Auth::user();
        $course = Course::findOrFail($courseId);

        // Check if user is enrolled in the course
        $enrollment = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $courseId)
            ->whereIn('status', ['active', 'completed'])
            ->first();

        if (!$enrollment) {
            return response()->json([
                'success' => false,
                'message' => 'You must be enrolled in this course to rate it'
            ], 403);
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if user has already rated this course
            $existingRating = CourseRating::where('user_id', $user->id)
                ->where('course_id', $courseId)
                ->first();

            if ($existingRating) {
                // Update existing rating
                $existingRating->update([
                    'rating' => $request->rating,
                    'review' => $request->review
                ]);
                $rating = $existingRating;
            } else {
                // Create new rating
                $rating = CourseRating::create([
                    'course_id' => $courseId,
                    'user_id' => $user->id,
                    'rating' => $request->rating,
                    'review' => $request->review
                ]);
            }

            // Update course rating statistics
            $course->updateRatingStats();

            return response()->json([
                'success' => true,
                'message' => 'Course rated successfully',
                'rating' => $rating,
                'course_stats' => [
                    'average_rating' => $course->fresh()->average_rating,
                    'total_ratings' => $course->fresh()->total_ratings
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to rate course: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get course ratings
     */
    public function getCourseRatings($courseId)
    {
        $course = Course::findOrFail($courseId);
        $user = Auth::user();

        $ratings = CourseRating::where('course_id', $courseId)
            ->with('user:id,first_name,last_name,username,avatar')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $userRating = null;
        if ($user) {
            $userRating = CourseRating::where('course_id', $courseId)
                ->where('user_id', $user->id)
                ->first();
        }

        return response()->json([
            'success' => true,
            'ratings' => $ratings,
            'course_stats' => [
                'average_rating' => $course->average_rating,
                'total_ratings' => $course->total_ratings
            ],
            'user_rating' => $userRating
        ]);
    }

    /**
     * Delete user's rating for a course
     */
    public function deleteRating($courseId)
    {
        $user = Auth::user();
        $course = Course::findOrFail($courseId);

        $rating = CourseRating::where('course_id', $courseId)
            ->where('user_id', $user->id)
            ->first();

        if (!$rating) {
            return response()->json([
                'success' => false,
                'message' => 'No rating found for this course'
            ], 404);
        }

        try {
            $rating->delete();
            
            // Update course rating statistics
            $course->updateRatingStats();

            return response()->json([
                'success' => true,
                'message' => 'Rating deleted successfully',
                'course_stats' => [
                    'average_rating' => $course->fresh()->average_rating,
                    'total_ratings' => $course->fresh()->total_ratings
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete rating: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if user can rate a course (is enrolled)
     */
    public function canRateCourse($courseId)
    {
        $user = Auth::user();
        $course = Course::findOrFail($courseId);

        $enrollment = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $courseId)
            ->whereIn('status', ['active', 'completed'])
            ->first();

        $hasRated = CourseRating::where('user_id', $user->id)
            ->where('course_id', $courseId)
            ->exists();

        return response()->json([
            'success' => true,
            'can_rate' => $enrollment !== null,
            'has_rated' => $hasRated,
            'enrollment_status' => $enrollment ? $enrollment->status : null
        ]);
    }
} 