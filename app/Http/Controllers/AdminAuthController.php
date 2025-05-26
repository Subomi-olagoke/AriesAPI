<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    /**
     * Show the admin login form
     */
    public function showLoginForm()
    {
        return view('admin.login');
    }

    /**
     * Handle admin login
     */
    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        // Determine if input is email or username
        $loginField = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        
        $credentials = [
            $loginField => $request->login,
            'password' => $request->password,
        ];

        // Add admin check to credentials
        $credentials['isAdmin'] = true;

        // Attempt login
        if (Auth::attempt($credentials, $request->filled('remember'))) {
            $request->session()->regenerate();
            
            // Always redirect to dashboard after login
            return redirect()->route('admin.dashboard');
        }

        throw ValidationException::withMessages([
            'login' => ['The provided credentials do not match our records or you do not have admin privileges.'],
        ]);
    }

    /**
     * Show the admin dashboard
     */
    public function dashboard()
    {
        // Import necessary classes
        $carbon = new \Carbon\Carbon();
        
        // Get user stats
        $totalUsers = \App\Models\User::count();
        $newUsersToday = \App\Models\User::whereDate('created_at', $carbon->today())->count();
        $newUsersThisWeek = \App\Models\User::where('created_at', '>=', $carbon->now()->subWeek())->count();
        $bannedUsers = \App\Models\User::where('is_banned', true)->count();
        
        // Get content stats
        $totalPosts = \App\Models\Post::count();
        $postsToday = \App\Models\Post::whereDate('created_at', $carbon->today())->count();
        $totalCourses = \App\Models\Course::count();
        $totalLibraries = \App\Models\OpenLibrary::count();
        
        // Try to get pending libraries count, but handle case where approval_status column might not exist
        try {
            $pendingLibraries = \App\Models\OpenLibrary::where('approval_status', 'pending')->count();
        } catch (\Exception $e) {
            // If column doesn't exist, default to 0
            $pendingLibraries = 0;
        }
        
        // Get payment stats
        try {
            $totalRevenue = \App\Models\PaymentLog::where('status', 'success')->sum('amount');
            $revenueThisMonth = \App\Models\PaymentLog::where('status', 'success')
                ->whereMonth('created_at', $carbon->month)
                ->whereYear('created_at', $carbon->year)
                ->sum('amount');
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle the case where the payment_logs table doesn't exist
            if (str_contains($e->getMessage(), "payment_logs' doesn't exist")) {
                $totalRevenue = 0;
                $revenueThisMonth = 0;
            } else {
                throw $e;
            }
        }
            
        // Get recent user registrations
        $recentUsers = \App\Models\User::with('profile')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
            
        // Get pending libraries for display in the libraries tab
        try {
            $pendingLibraries = \App\Models\OpenLibrary::where('approval_status', 'pending')
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        } catch (\Exception $e) {
            $pendingLibraries = collect(); // Empty collection if error
        }
        
        // Get waitlist stats
        try {
            $totalWaitlist = \App\Models\Waitlist::count();
            $waitlistThisWeek = \App\Models\Waitlist::where('created_at', '>=', $carbon->now()->subWeek())->count();
            $recentWaitlistEntries = \App\Models\Waitlist::orderBy('created_at', 'desc')->limit(5)->get();
            $totalWaitlistEmails = \App\Models\WaitlistEmail::count();
        } catch (\Exception $e) {
            $totalWaitlist = 0;
            $waitlistThisWeek = 0;
            $recentWaitlistEntries = collect();
            $totalWaitlistEmails = 0;
        }
        
        // Compile stats for the dashboard
        $stats = [
            'users' => [
                'total' => $totalUsers,
                'new_today' => $newUsersToday,
                'new_this_week' => $newUsersThisWeek,
                'banned' => $bannedUsers,
            ],
            'content' => [
                'total_posts' => $totalPosts,
                'posts_today' => $postsToday,
                'total_courses' => $totalCourses,
                'total_libraries' => $totalLibraries,
                'pending_libraries' => $pendingLibraries,
            ],
            'revenue' => [
                'total' => $totalRevenue,
                'this_month' => $revenueThisMonth,
            ],
            'waitlist' => [
                'total' => $totalWaitlist,
                'new_this_week' => $waitlistThisWeek,
                'total_emails' => $totalWaitlistEmails,
            ],
        ];
        
        // Ensure all expected data is available, especially when working with Blade templates
        // that might expect these values to be set
        $defaultStats = [
            'users' => [
                'total' => 0,
                'new_today' => 0,
                'new_this_week' => 0,
                'banned' => 0,
            ],
            'content' => [
                'total_posts' => 0,
                'posts_today' => 0,
                'total_courses' => 0,
                'total_libraries' => 0,
                'pending_libraries' => 0,
            ],
            'revenue' => [
                'total' => 0,
                'this_month' => 0,
            ],
            'waitlist' => [
                'total' => 0,
                'new_this_week' => 0,
                'total_emails' => 0,
            ],
        ];
        
        // Merge default stats with actual stats to ensure all keys exist
        $mergedStats = array_replace_recursive($defaultStats, $stats);
        
        // Add app store data
        $appStoreData = [
            'status' => 'pending', // Can be 'pending', 'approved', 'rejected'
            'version' => '1.1.0',
            'submitted_at' => 'May 25, 2025',
            'notes' => 'Waiting for App Store review'
        ];
        
        // Generate API endpoint URL for charts
        $statsApiUrl = route('admin.api.dashboard-stats');
        
        // Use the original dashboard layout
        return view('admin.dashboard', [
            'stats' => $mergedStats,
            'recentUsers' => $recentUsers,
            'pendingLibraries' => $pendingLibraries ?? collect(),
            'recentWaitlistEntries' => $recentWaitlistEntries ?? collect(),
            'appStore' => $appStoreData,
            'statsApiUrl' => $statsApiUrl
        ]);
    }

    /**
     * Export dashboard statistics as CSV
     */
    public function exportStats()
    {
        if (!Auth::user() || Auth::user()->isAdmin !== true) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        
        // Get the same stats as the dashboard
        $carbon = new \Carbon\Carbon();
        
        // Get user stats
        $totalUsers = \App\Models\User::count();
        $newUsersToday = \App\Models\User::whereDate('created_at', $carbon->today())->count();
        $newUsersThisWeek = \App\Models\User::where('created_at', '>=', $carbon->now()->subWeek())->count();
        $bannedUsers = \App\Models\User::where('is_banned', true)->count();
        
        // Get content stats
        $totalPosts = \App\Models\Post::count();
        $postsToday = \App\Models\Post::whereDate('created_at', $carbon->today())->count();
        $totalCourses = \App\Models\Course::count();
        $totalLibraries = \App\Models\OpenLibrary::count();
        
        // Get pending libraries count
        try {
            $pendingLibraries = \App\Models\OpenLibrary::where('approval_status', 'pending')->count();
        } catch (\Exception $e) {
            $pendingLibraries = 0;
        }
        
        // Get payment stats
        try {
            $totalRevenue = \App\Models\PaymentLog::where('status', 'success')->sum('amount');
            $revenueThisMonth = \App\Models\PaymentLog::where('status', 'success')
                ->whereMonth('created_at', $carbon->month)
                ->whereYear('created_at', $carbon->year)
                ->sum('amount');
        } catch (\Illuminate\Database\QueryException $e) {
            $totalRevenue = 0;
            $revenueThisMonth = 0;
        }
        
        // Get waitlist stats
        try {
            $totalWaitlist = \App\Models\Waitlist::count();
            $waitlistThisWeek = \App\Models\Waitlist::where('created_at', '>=', $carbon->now()->subWeek())->count();
            $totalWaitlistEmails = \App\Models\WaitlistEmail::count();
        } catch (\Exception $e) {
            $totalWaitlist = 0;
            $waitlistThisWeek = 0;
            $totalWaitlistEmails = 0;
        }
        
        // Compile stats for the CSV
        $stats = [
            'Date' => $carbon->toDateString(),
            'Total Users' => $totalUsers,
            'New Users Today' => $newUsersToday,
            'New Users This Week' => $newUsersThisWeek,
            'Banned Users' => $bannedUsers,
            'Total Posts' => $totalPosts,
            'Posts Today' => $postsToday,
            'Total Courses' => $totalCourses,
            'Total Libraries' => $totalLibraries,
            'Pending Libraries' => $pendingLibraries,
            'Total Revenue' => $totalRevenue,
            'Revenue This Month' => $revenueThisMonth,
            'Total Waitlist' => $totalWaitlist,
            'New Waitlist This Week' => $waitlistThisWeek,
            'Total Waitlist Emails' => $totalWaitlistEmails,
        ];
        
        // Generate CSV content
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="dashboard-stats-' . $carbon->toDateString() . '.csv"',
        ];
        
        $callback = function() use ($stats) {
            $file = fopen('php://output', 'w');
            
            // Add headers
            fputcsv($file, array_keys($stats));
            
            // Add values
            fputcsv($file, array_values($stats));
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }
    
    /**
     * Log the admin out
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}