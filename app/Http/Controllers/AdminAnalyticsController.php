<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\OpenLibrary;
use App\Models\Readlist;
use App\Models\LibraryUrl;
use App\Models\Like;
use App\Models\Vote;
use App\Models\Follow;
use App\Models\AlexPointsTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AdminAnalyticsController extends Controller
{
    /**
     * Get overview dashboard summary stats.
     * GET /api/admin/analytics/overview
     */
    public function overview()
    {
        // Cache for 5 minutes to reduce database load
        return Cache::remember('admin_analytics_overview', 300, function () {
            $now = Carbon::now();
            $today = $now->copy()->startOfDay();
            $weekAgo = $now->copy()->subDays(7)->startOfDay();

            return response()->json([
                'total_users' => User::count(),
                'active_users_today' => User::where('updated_at', '>=', $today)->count(),
                'active_users_week' => User::where('updated_at', '>=', $weekAgo)->count(),
                'total_libraries' => OpenLibrary::count(),
                'total_readlists' => Readlist::count(),
                'total_library_urls' => LibraryUrl::count(),
                'new_users_today' => User::where('created_at', '>=', $today)->count(),
                'new_users_week' => User::where('created_at', '>=', $weekAgo)->count(),
            ]);
        });
    }

    /**
     * Get user activity metrics.
     * GET /api/admin/analytics/users
     */
    public function users(Request $request)
    {
        $period = $request->get('period', 'week'); // day, week, month
        $now = Carbon::now();

        switch ($period) {
            case 'day':
                $startDate = $now->copy()->startOfDay();
                break;
            case 'month':
                $startDate = $now->copy()->subDays(30)->startOfDay();
                break;
            default: // week
                $startDate = $now->copy()->subDays(7)->startOfDay();
        }

        // User role distribution
        $roleDistribution = User::select('role', DB::raw('count(*) as count'))
            ->groupBy('role')
            ->get()
            ->pluck('count', 'role');

        // New users by day
        $newUsersByDay = User::where('created_at', '>=', $startDate)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Top users by alex points
        $topUsersByPoints = User::orderBy('alex_points', 'desc')
            ->take(10)
            ->get(['id', 'username', 'alex_points', 'point_level', 'avatar']);

        // Verified users count
        $verifiedUsers = User::where('is_verified', true)->count();
        $bannedUsers = User::where('is_banned', true)->count();

        return response()->json([
            'role_distribution' => $roleDistribution,
            'new_users_by_day' => $newUsersByDay,
            'top_users_by_points' => $topUsersByPoints,
            'verified_users' => $verifiedUsers,
            'banned_users' => $bannedUsers,
            'period' => $period,
        ]);
    }

    /**
     * Get content statistics (libraries, readlists, URLs).
     * GET /api/admin/analytics/content
     */
    public function content(Request $request)
    {
        $period = $request->get('period', 'week');
        $now = Carbon::now();

        switch ($period) {
            case 'day':
                $startDate = $now->copy()->startOfDay();
                break;
            case 'month':
                $startDate = $now->copy()->subDays(30)->startOfDay();
                break;
            default:
                $startDate = $now->copy()->subDays(7)->startOfDay();
        }

        // Library stats
        $libraryStats = [
            'total' => OpenLibrary::count(),
            'new_this_period' => OpenLibrary::where('created_at', '>=', $startDate)->count(),
            'by_type' => OpenLibrary::select('type', DB::raw('count(*) as count'))
                ->groupBy('type')
                ->get()
                ->pluck('count', 'type'),
            'by_approval_status' => OpenLibrary::select('approval_status', DB::raw('count(*) as count'))
                ->groupBy('approval_status')
                ->get()
                ->pluck('count', 'approval_status'),
        ];

        // Readlist stats
        $readlistStats = [
            'total' => Readlist::count(),
            'new_this_period' => Readlist::where('created_at', '>=', $startDate)->count(),
            'public' => Readlist::where('is_public', true)->count(),
            'private' => Readlist::where('is_public', false)->count(),
        ];

        // Library URL stats
        $urlStats = [
            'total' => LibraryUrl::count(),
            'new_this_period' => LibraryUrl::where('created_at', '>=', $startDate)->count(),
        ];

        // Top libraries by URL count
        $topLibraries = OpenLibrary::withCount('urls')
            ->orderBy('urls_count', 'desc')
            ->take(10)
            ->get(['id', 'name', 'type', 'urls_count']);

        return response()->json([
            'libraries' => $libraryStats,
            'readlists' => $readlistStats,
            'urls' => $urlStats,
            'top_libraries' => $topLibraries,
            'period' => $period,
        ]);
    }

    /**
     * Get engagement metrics (likes, votes, follows).
     * GET /api/admin/analytics/engagement
     */
    public function engagement(Request $request)
    {
        $period = $request->get('period', 'week');
        $now = Carbon::now();

        switch ($period) {
            case 'day':
                $startDate = $now->copy()->startOfDay();
                break;
            case 'month':
                $startDate = $now->copy()->subDays(30)->startOfDay();
                break;
            default:
                $startDate = $now->copy()->subDays(7)->startOfDay();
        }

        // Like stats
        $likeStats = [
            'total' => Like::count(),
            'this_period' => Like::where('created_at', '>=', $startDate)->count(),
        ];

        // Vote stats (upvotes/downvotes)
        $voteStats = [
            'total' => Vote::count(),
            'upvotes' => Vote::where('vote_type', 'up')->count(),
            'downvotes' => Vote::where('vote_type', 'down')->count(),
            'this_period' => Vote::where('created_at', '>=', $startDate)->count(),
        ];

        // Follow stats
        $followStats = [
            'total_follows' => Follow::count(),
            'new_this_period' => Follow::where('created_at', '>=', $startDate)->count(),
        ];

        // AlexPoints activity
        $pointsStats = [
            'total_transactions' => AlexPointsTransaction::count(),
            'this_period' => AlexPointsTransaction::where('created_at', '>=', $startDate)->count(),
            'total_points_earned' => AlexPointsTransaction::where('points', '>', 0)->sum('points'),
            'total_points_spent' => abs(AlexPointsTransaction::where('points', '<', 0)->sum('points')),
        ];

        // Engagement by day
        $engagementByDay = DB::table('likes')
            ->where('created_at', '>=', $startDate)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as likes'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'likes' => $likeStats,
            'votes' => $voteStats,
            'follows' => $followStats,
            'alex_points' => $pointsStats,
            'engagement_by_day' => $engagementByDay,
            'period' => $period,
        ]);
    }

    /**
     * Get growth metrics over time.
     * GET /api/admin/analytics/growth
     */
    public function growth(Request $request)
    {
        $period = $request->get('period', 'month'); // week, month, year
        $now = Carbon::now();

        switch ($period) {
            case 'week':
                $startDate = $now->copy()->subDays(7)->startOfDay();
                $groupFormat = '%Y-%m-%d';
                break;
            case 'year':
                $startDate = $now->copy()->subYear()->startOfDay();
                $groupFormat = '%Y-%m';
                break;
            default: // month
                $startDate = $now->copy()->subDays(30)->startOfDay();
                $groupFormat = '%Y-%m-%d';
        }

        // User growth
        $userGrowth = User::where('created_at', '>=', $startDate)
            ->select(DB::raw("DATE_FORMAT(created_at, '$groupFormat') as period"), DB::raw('count(*) as count'))
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // Library growth
        $libraryGrowth = OpenLibrary::where('created_at', '>=', $startDate)
            ->select(DB::raw("DATE_FORMAT(created_at, '$groupFormat') as period"), DB::raw('count(*) as count'))
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // Readlist growth
        $readlistGrowth = Readlist::where('created_at', '>=', $startDate)
            ->select(DB::raw("DATE_FORMAT(created_at, '$groupFormat') as period"), DB::raw('count(*) as count'))
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // Cumulative totals
        $cumulativeUsers = User::where('created_at', '>=', $startDate)
            ->select(
                DB::raw("DATE_FORMAT(created_at, '$groupFormat') as period"),
                DB::raw('(SELECT COUNT(*) FROM users WHERE created_at <= MAX(u.created_at)) as cumulative')
            )
            ->from('users as u')
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return response()->json([
            'user_growth' => $userGrowth,
            'library_growth' => $libraryGrowth,
            'readlist_growth' => $readlistGrowth,
            'period' => $period,
            'start_date' => $startDate->toISOString(),
        ]);
    }

    /**
     * Get currently active users list.
     * GET /api/admin/analytics/active-users
     */
    public function activeUsers(Request $request)
    {
        $limit = min($request->get('limit', 50), 100);
        $hoursAgo = $request->get('hours', 24);

        $cutoffTime = Carbon::now()->subHours($hoursAgo);

        $activeUsers = User::where('updated_at', '>=', $cutoffTime)
            ->orderBy('updated_at', 'desc')
            ->take($limit)
            ->get(['id', 'username', 'avatar', 'alex_points', 'point_level', 'role', 'updated_at']);

        // Add additional metrics for each user
        $usersWithMetrics = $activeUsers->map(function ($user) use ($cutoffTime) {
            // Count libraries created by user in the period
            $librariesCreated = OpenLibrary::where('user_id', $user->id)
                ->where('created_at', '>=', $cutoffTime)
                ->count();

            // Count readlists created by user in the period
            $readlistsCreated = Readlist::where('user_id', $user->id)
                ->where('created_at', '>=', $cutoffTime)
                ->count();

            return [
                'id' => $user->id,
                'username' => $user->username,
                'avatar' => $user->avatar,
                'alex_points' => $user->alex_points,
                'point_level' => $user->point_level,
                'role' => $user->role,
                'last_active' => $user->updated_at->toISOString(),
                'libraries_created_today' => $librariesCreated,
                'readlists_created_today' => $readlistsCreated,
            ];
        });

        return response()->json([
            'users' => $usersWithMetrics,
            'total' => $activeUsers->count(),
            'hours_period' => $hoursAgo,
        ]);
    }
}
