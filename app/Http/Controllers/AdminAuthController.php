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
        ];
        
        return view('admin.dashboard', [
            'stats' => $stats,
            'recentUsers' => $recentUsers
        ]);
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