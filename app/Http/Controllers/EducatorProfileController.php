<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EducatorProfileController extends Controller
{
    /**
     * Display the educator's profile with all relevant information for hiring.
     */
    public function show($username)
    {
        // Find the educator by username
        $educator = User::where('username', $username)
            ->where('role', User::ROLE_EDUCATOR)
            ->with(['profile', 'topic', 'courses'])
            ->first();

        if (!$educator) {
            return response()->json([
                'message' => 'Educator not found'
            ], 404);
        }

        // Get the educator's ratings (average) from completed courses
        $avgRating = DB::table('course_enrollments')
            ->join('courses', 'course_enrollments.course_id', '=', 'courses.id')
            ->where('courses.user_id', $educator->id)
            ->where('course_enrollments.status', 'completed')
            ->whereNotNull('course_enrollments.feedback_rating')
            ->avg('course_enrollments.feedback_rating') ?? 0;

        // Get testimonials/reviews
        $testimonials = DB::table('course_enrollments')
            ->join('courses', 'course_enrollments.course_id', '=', 'courses.id')
            ->join('users', 'course_enrollments.user_id', '=', 'users.id')
            ->where('courses.user_id', $educator->id)
            ->where('course_enrollments.status', 'completed')
            ->whereNotNull('course_enrollments.feedback_comment')
            ->select(
                'users.username',
                'users.first_name',
                'users.last_name',
                'users.avatar',
                'course_enrollments.feedback_rating',
                'course_enrollments.feedback_comment',
                'course_enrollments.updated_at'
            )
            ->orderBy('course_enrollments.updated_at', 'desc')
            ->limit(5)
            ->get();

        // Get educator stats
        $studentCount = DB::table('course_enrollments')
            ->join('courses', 'course_enrollments.course_id', '=', 'courses.id')
            ->where('courses.user_id', $educator->id)
            ->distinct('course_enrollments.user_id')
            ->count('course_enrollments.user_id');

        $courseCount = $educator->courses()->count();
        $totalHours = $educator->courses()->sum('duration_minutes') / 60;

        // Format the educator's profile for hiring
        $profile = [
            'id' => $educator->id,
            'username' => $educator->username,
            'full_name' => $educator->first_name . ' ' . $educator->last_name,
            'avatar' => $educator->avatar,
            'bio' => $educator->profile ? $educator->profile->bio : null,
            'topics' => $educator->topic->pluck('name'),
            'average_rating' => round($avgRating, 1),
            'stats' => [
                'students' => $studentCount,
                'courses' => $courseCount,
                'teaching_hours' => round($totalHours, 1),
                'followers' => $educator->followers()->count(),
            ],
            'testimonials' => $testimonials,
            'qualifications' => $educator->profile ? ($educator->profile->qualifications ?? []) : [],
            'teaching_style' => $educator->profile ? ($educator->profile->teaching_style ?? null) : null,
            'availability' => $educator->profile ? ($educator->profile->availability ?? []) : [],
            'hire_rate' => $educator->profile ? ($educator->profile->hire_rate ?? 0) : 0,
            'hire_currency' => $educator->profile ? ($educator->profile->hire_currency ?? 'USD') : 'USD',
            'social_links' => $educator->profile ? ($educator->profile->social_links ?? []) : [],
        ];

        return response()->json([
            'message' => 'Educator profile retrieved successfully',
            'educator' => $profile
        ]);
    }
}