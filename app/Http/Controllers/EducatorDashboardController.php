<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EducatorDashboardController extends Controller
{
    /**
     * Show the educator dashboard
     */
    public function index()
    {
        $user = Auth::user();
        
        // Ensure user is an educator
        if ($user->role != User::ROLE_EDUCATOR) {
            return redirect()->route('home')->with('error', 'You do not have permission to access the educator dashboard');
        }
        
        // Get educator stats
        $courseCount = Course::where('user_id', $user->id)->count();
        
        // Get student count (distinct enrolled users)
        $studentCount = CourseEnrollment::whereHas('course', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->distinct('user_id')
        ->count('user_id');
        
        // Get total earnings
        $totalEarnings = DB::table('course_enrollments')
            ->join('courses', 'course_enrollments.course_id', '=', 'courses.id')
            ->where('courses.user_id', $user->id)
            ->where('course_enrollments.status', 'active')
            ->sum(DB::raw('courses.price'));
            
        // Get recent activities
        $recentEnrollments = CourseEnrollment::whereHas('course', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->with(['course', 'user'])
        ->orderBy('created_at', 'desc')
        ->take(5)
        ->get()
        ->map(function ($enrollment) {
            return (object) [
                'type' => 'New Enrollment',
                'course_title' => $enrollment->course->title,
                'created_at' => $enrollment->created_at,
                'link' => route('educator.courses.show', $enrollment->course_id)
            ];
        });
        
        $recentCourses = Course::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($course) {
                return (object) [
                    'type' => 'Course Created',
                    'course_title' => $course->title,
                    'created_at' => $course->created_at,
                    'link' => route('educator.courses.show', $course->id)
                ];
            });
            
        // Combine and sort activities
        $recentActivities = $recentEnrollments->merge($recentCourses)
            ->sortByDesc('created_at')
            ->take(10);
        
        return view('educators.dashboard', compact('courseCount', 'studentCount', 'totalEarnings', 'recentActivities'));
    }
    
    /**
     * Get dashboard stats for API
     */
    public function getDashboardStats()
    {
        $user = Auth::user();
        
        // Ensure user is an educator
        if ($user->role != User::ROLE_EDUCATOR) {
            return response()->json(['error' => 'Unauthorized access'], 403);
        }
        
        // Get educator stats
        $courseCount = Course::where('user_id', $user->id)->count();
        
        // Get student count (distinct enrolled users)
        $studentCount = CourseEnrollment::whereHas('course', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->distinct('user_id')
        ->count('user_id');
        
        // Get total earnings
        $totalEarnings = DB::table('course_enrollments')
            ->join('courses', 'course_enrollments.course_id', '=', 'courses.id')
            ->where('courses.user_id', $user->id)
            ->where('course_enrollments.status', 'active')
            ->sum(DB::raw('courses.price'));
            
        // Get recent activities
        $recentEnrollments = CourseEnrollment::whereHas('course', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->with(['course:id,title', 'user:id,username,first_name,last_name,avatar'])
        ->orderBy('created_at', 'desc')
        ->take(5)
        ->get()
        ->map(function ($enrollment) {
            return [
                'id' => $enrollment->id,
                'type' => 'enrollment',
                'course_id' => $enrollment->course_id,
                'course_title' => $enrollment->course->title,
                'user' => [
                    'id' => $enrollment->user->id,
                    'username' => $enrollment->user->username,
                    'name' => $enrollment->user->first_name . ' ' . $enrollment->user->last_name,
                    'avatar' => $enrollment->user->avatar
                ],
                'created_at' => $enrollment->created_at->format('Y-m-d H:i:s')
            ];
        });
        
        $recentCourses = Course::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($course) {
                return [
                    'id' => $course->id,
                    'type' => 'course_created',
                    'course_id' => $course->id,
                    'course_title' => $course->title,
                    'created_at' => $course->created_at->format('Y-m-d H:i:s')
                ];
            });
            
        // Get data for charts
        $monthlyData = $this->getMonthlyEnrollmentsAndEarnings($user->id);
            
        return response()->json([
            'stats' => [
                'course_count' => $courseCount,
                'student_count' => $studentCount,
                'total_earnings' => $totalEarnings
            ],
            'recent_activity' => $recentEnrollments->merge($recentCourses)->sortByDesc('created_at')->take(10)->values(),
            'monthly_data' => $monthlyData
        ]);
    }
    
    /**
     * Get monthly enrollments and earnings for chart data
     */
    private function getMonthlyEnrollmentsAndEarnings($userId)
    {
        // Get data for the last 6 months
        $startDate = now()->subMonths(5)->startOfMonth();
        $endDate = now()->endOfMonth();
        
        $monthlyEnrollments = DB::table('course_enrollments')
            ->join('courses', 'course_enrollments.course_id', '=', 'courses.id')
            ->where('courses.user_id', $userId)
            ->whereBetween('course_enrollments.created_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE_FORMAT(course_enrollments.created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as enrollment_count'),
                DB::raw('SUM(courses.price) as earnings')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();
            
        // Initialize all months with zero values
        $monthLabels = [];
        $monthlyData = [];
        
        // Initialize all months in range
        $currentDate = clone $startDate;
        while ($currentDate <= $endDate) {
            $monthKey = $currentDate->format('Y-m');
            $monthLabels[] = $currentDate->format('M Y');
            $monthlyData[$monthKey] = [
                'enrollments' => 0,
                'earnings' => 0
            ];
            $currentDate->addMonth();
        }
        
        // Fill in actual data
        foreach ($monthlyEnrollments as $data) {
            $monthlyData[$data->month] = [
                'enrollments' => $data->enrollment_count,
                'earnings' => $data->earnings
            ];
        }
        
        return [
            'labels' => $monthLabels,
            'enrollments' => array_map(function ($data) {
                return $data['enrollments'];
            }, $monthlyData),
            'earnings' => array_map(function ($data) {
                return $data['earnings'];
            }, $monthlyData)
        ];
    }
    
    /**
     * Show educator's students
     */
    public function students()
    {
        $user = Auth::user();
        
        // Get all students enrolled in educator's courses
        $students = User::whereHas('enrollments.course', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->withCount(['enrollments' => function ($query) use ($user) {
            $query->whereHas('course', function ($subQuery) use ($user) {
                $subQuery->where('user_id', $user->id);
            });
        }])
        ->with(['enrollments' => function ($query) use ($user) {
            $query->whereHas('course', function ($subQuery) use ($user) {
                $subQuery->where('user_id', $user->id);
            })->with('course');
        }])
        ->paginate(15);
        
        return view('educators.students', compact('students'));
    }
    
    /**
     * Show educator's earnings dashboard
     */
    public function earnings()
    {
        $user = Auth::user();
        
        // Get monthly earnings data for the last 12 months
        $monthlyEarnings = DB::table('course_enrollments')
            ->join('courses', 'course_enrollments.course_id', '=', 'courses.id')
            ->where('courses.user_id', $user->id)
            ->where('course_enrollments.status', 'active')
            ->select(
                DB::raw('YEAR(course_enrollments.created_at) as year'),
                DB::raw('MONTH(course_enrollments.created_at) as month'),
                DB::raw('SUM(courses.price) as total')
            )
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->take(12)
            ->get();
            
        // Get earnings by course
        $earningsByCourse = DB::table('course_enrollments')
            ->join('courses', 'course_enrollments.course_id', '=', 'courses.id')
            ->where('courses.user_id', $user->id)
            ->where('course_enrollments.status', 'active')
            ->select(
                'courses.id',
                'courses.title',
                DB::raw('COUNT(course_enrollments.id) as enrollment_count'),
                DB::raw('SUM(courses.price) as total')
            )
            ->groupBy('courses.id', 'courses.title')
            ->orderBy('total', 'desc')
            ->get();
            
        // Calculate total earnings
        $totalEarnings = $earningsByCourse->sum('total');
        
        // Calculate earnings growth month-over-month
        $currentMonth = now()->format('Y-m');
        $lastMonth = now()->subMonth()->format('Y-m');
        
        $currentMonthEarnings = DB::table('course_enrollments')
            ->join('courses', 'course_enrollments.course_id', '=', 'courses.id')
            ->where('courses.user_id', $user->id)
            ->where('course_enrollments.status', 'active')
            ->whereRaw("DATE_FORMAT(course_enrollments.created_at, '%Y-%m') = ?", [$currentMonth])
            ->sum('courses.price');
            
        $lastMonthEarnings = DB::table('course_enrollments')
            ->join('courses', 'course_enrollments.course_id', '=', 'courses.id')
            ->where('courses.user_id', $user->id)
            ->where('course_enrollments.status', 'active')
            ->whereRaw("DATE_FORMAT(course_enrollments.created_at, '%Y-%m') = ?", [$lastMonth])
            ->sum('courses.price');
            
        $earningsGrowth = 0;
        if ($lastMonthEarnings > 0) {
            $earningsGrowth = (($currentMonthEarnings - $lastMonthEarnings) / $lastMonthEarnings) * 100;
        }
        
        return view('educators.earnings', compact('monthlyEarnings', 'earningsByCourse', 'totalEarnings', 'currentMonthEarnings', 'lastMonthEarnings', 'earningsGrowth'));
    }
    
    /**
     * Show educator settings page
     */
    public function settings()
    {
        $user = Auth::user();
        $profile = $user->profile;
        
        return view('educators.settings', compact('user', 'profile'));
    }
    
    /**
     * Update educator profile settings
     */
    public function updateSettings(Request $request)
    {
        $user = Auth::user();
        
        $this->validate($request, [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'bio' => 'nullable|string|max:1000',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'hire_rate' => 'nullable|numeric|min:0',
            'hire_currency' => 'nullable|string|in:NGN,USD,EUR,GBP'
        ]);
        
        // Update user data
        $user->first_name = $request->first_name;
        $user->last_name = $request->last_name;
        $user->save();
        
        // Update profile data
        $profile = $user->profile;
        if (!$profile) {
            $profile = new \App\Models\Profile(['user_id' => $user->id]);
        }
        
        $profile->bio = $request->bio;
        
        if ($request->filled('hire_rate')) {
            $profile->hire_rate = $request->hire_rate;
            $profile->hire_currency = $request->hire_currency ?? 'NGN';
        }
        
        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            $fileUploadService = app(\App\Services\FileUploadService::class);
            $user->avatar = $fileUploadService->uploadFile(
                $request->file('avatar'),
                'avatars',
                [
                    'process_image' => true,
                    'width' => 300,
                    'height' => 300,
                    'fit' => true
                ]
            );
            $user->save();
        }
        
        $profile->save();
        
        return redirect()->route('educator.settings')->with('success', 'Your profile has been updated successfully.');
    }
}