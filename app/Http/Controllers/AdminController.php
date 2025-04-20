<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Post;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Report;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\LiveClass;
use App\Models\Message;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Check if the authenticated user is an admin
     *
     * @return bool
     */
    private function isAdmin()
    {
        return Auth::user()->is_admin === true;
    }

    /**
     * Ban a user
     *
     * @param Request $request
     * @param string $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function banUser(Request $request, $userId)
    {
        if (!$this->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $user = User::findOrFail($userId);

        // Don't allow banning other admins
        if ($user->is_admin) {
            return response()->json([
                'message' => 'Cannot ban an administrator'
            ], 403);
        }

        $user->is_banned = true;
        $user->banned_at = now();
        $user->ban_reason = $request->reason;

        if ($user->save()) {
            // Revoke all user tokens to force logout
            $user->tokens()->delete();

            return response()->json([
                'message' => 'User has been banned successfully',
                'user' => $user
            ]);
        }

        return response()->json([
            'message' => 'Failed to ban user'
        ], 500);
    }

    /**
     * Unban a user
     *
     * @param string $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function unbanUser($userId)
    {
        if (!$this->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $user = User::findOrFail($userId);

        $user->is_banned = false;
        $user->banned_at = null;
        $user->ban_reason = null;

        if ($user->save()) {
            return response()->json([
                'message' => 'User has been unbanned successfully',
                'user' => $user
            ]);
        }

        return response()->json([
            'message' => 'Failed to unban user'
        ], 500);
    }

    /**
     * Get a list of banned users
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBannedUsers(Request $request)
    {
        if (!$this->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $bannedUsers = User::where('is_banned', true)
            ->orderBy('banned_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($bannedUsers);
    }

    /**
     * Get app overview statistics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAppOverview()
    {
        if (!$this->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $totalUsers = User::count();
        $newUsersToday = User::whereDate('created_at', Carbon::today())->count();
        $newUsersThisWeek = User::where('created_at', '>=', Carbon::now()->subWeek())->count();
        $newUsersThisMonth = User::where('created_at', '>=', Carbon::now()->startOfMonth())->count();

        $totalPosts = Post::count();
        $totalCourses = Course::count();
        $totalEnrollments = CourseEnrollment::count();
        
        $activeUsers = User::where('created_at', '>=', Carbon::now()->subDays(30))
            ->orWhereHas('posts', function($query) {
                $query->where('created_at', '>=', Carbon::now()->subDays(30));
            })
            ->orWhereHas('comments', function($query) {
                $query->where('created_at', '>=', Carbon::now()->subDays(30));
            })
            ->count();

        $pendingReports = Report::where('status', 'pending')->count();

        return response()->json([
            'users' => [
                'total' => $totalUsers,
                'new_today' => $newUsersToday,
                'new_this_week' => $newUsersThisWeek,
                'new_this_month' => $newUsersThisMonth,
                'active_last_30_days' => $activeUsers,
                'banned' => User::where('is_banned', true)->count(),
            ],
            'content' => [
                'total_posts' => $totalPosts,
                'posts_today' => Post::whereDate('created_at', Carbon::today())->count(),
                'posts_this_week' => Post::where('created_at', '>=', Carbon::now()->subWeek())->count(),
                'total_courses' => $totalCourses,
                'courses_this_month' => Course::where('created_at', '>=', Carbon::now()->startOfMonth())->count(),
                'total_enrollments' => $totalEnrollments,
                'enrollments_this_month' => CourseEnrollment::where('created_at', '>=', Carbon::now()->startOfMonth())->count(),
            ],
            'reports' => [
                'pending' => $pendingReports,
                'total' => Report::count(),
            ],
            'live_classes' => [
                'total' => LiveClass::count(),
                'active' => LiveClass::where('status', 'active')->count(),
                'this_week' => LiveClass::where('created_at', '>=', Carbon::now()->subWeek())->count(),
            ],
        ]);
    }

    /**
     * Get user growth statistics
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserGrowth(Request $request)
    {
        if (!$this->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $period = $request->period ?? 'month';
        $duration = $request->duration ?? 12;
        
        switch ($period) {
            case 'day':
                $groupFormat = 'Y-m-d';
                $startDate = Carbon::now()->subDays($duration);
                break;
            case 'week':
                $groupFormat = 'Y-W';
                $startDate = Carbon::now()->subWeeks($duration);
                break;
            case 'month':
            default:
                $groupFormat = 'Y-m';
                $startDate = Carbon::now()->subMonths($duration);
                break;
        }

        $userGrowth = User::where('created_at', '>=', $startDate)
            ->select(DB::raw('DATE_FORMAT(created_at, "' . $groupFormat . '") as period'), 
                    DB::raw('count(*) as count'))
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return response()->json([
            'period' => $period,
            'data' => $userGrowth
        ]);
    }

    /**
     * Get revenue statistics
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRevenueStats(Request $request)
    {
        if (!$this->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $period = $request->period ?? 'month';
        $duration = $request->duration ?? 12;
        
        switch ($period) {
            case 'day':
                $groupFormat = 'Y-m-d';
                $startDate = Carbon::now()->subDays($duration);
                break;
            case 'week':
                $groupFormat = 'Y-W';
                $startDate = Carbon::now()->subWeeks($duration);
                break;
            case 'month':
            default:
                $groupFormat = 'Y-m';
                $startDate = Carbon::now()->subMonths($duration);
                break;
        }

        // Get course enrollment revenue
        $courseRevenue = Payment::where('created_at', '>=', $startDate)
            ->where('status', 'successful')
            ->where('payment_type', 'course')
            ->select(
                DB::raw('DATE_FORMAT(created_at, "' . $groupFormat . '") as period'),
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // Get subscription revenue
        $subscriptionRevenue = Payment::where('created_at', '>=', $startDate)
            ->where('status', 'successful')
            ->where('payment_type', 'subscription')
            ->select(
                DB::raw('DATE_FORMAT(created_at, "' . $groupFormat . '") as period'),
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();
            
        // Get tutoring revenue
        $tutoringRevenue = Payment::where('created_at', '>=', $startDate)
            ->where('status', 'successful')
            ->where('payment_type', 'tutoring')
            ->select(
                DB::raw('DATE_FORMAT(created_at, "' . $groupFormat . '") as period'),
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // Total revenue
        $totalRevenue = Payment::where('status', 'successful')->sum('amount');
        $totalTransactions = Payment::where('status', 'successful')->count();
        $revenueThisMonth = Payment::where('status', 'successful')
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->sum('amount');

        return response()->json([
            'period' => $period,
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_transactions' => $totalTransactions,
                'revenue_this_month' => $revenueThisMonth,
                'average_transaction' => $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0,
            ],
            'course_revenue' => $courseRevenue,
            'subscription_revenue' => $subscriptionRevenue,
            'tutoring_revenue' => $tutoringRevenue
        ]);
    }

    /**
     * Get content engagement metrics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getContentEngagement()
    {
        if (!$this->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        // Most popular courses by enrollment
        $topCourses = Course::withCount('enrollments')
            ->orderBy('enrollments_count', 'desc')
            ->limit(10)
            ->get();

        // Most popular educators by followers
        $topEducators = User::where('role', User::ROLE_EDUCATOR)
            ->withCount('followers')
            ->orderBy('followers_count', 'desc')
            ->limit(10)
            ->get(['id', 'username', 'first_name', 'last_name', 'avatar', 'followers_count']);

        // Most liked posts
        $topPosts = Post::withCount('likes')
            ->orderBy('likes_count', 'desc')
            ->limit(10)
            ->get();

        // Most active live classes
        $topLiveClasses = LiveClass::withCount('participants')
            ->orderBy('participants_count', 'desc')
            ->limit(10)
            ->get();

        // Engagement by topic
        $topicEngagement = DB::table('topics')
            ->select(
                'topics.id',
                'topics.name',
                DB::raw('COUNT(DISTINCT user_topic.user_id) as followers_count'),
                DB::raw('COUNT(DISTINCT courses.id) as courses_count'),
                DB::raw('COUNT(DISTINCT posts.id) as posts_count')
            )
            ->leftJoin('user_topic', 'topics.id', '=', 'user_topic.topic_id')
            ->leftJoin('courses', 'topics.id', '=', 'courses.topic_id')
            ->leftJoin('posts', function ($join) {
                $join->on('topics.id', '=', DB::raw('JSON_CONTAINS(posts.topics, CAST(topics.id AS CHAR))'));
            })
            ->groupBy('topics.id', 'topics.name')
            ->orderBy('followers_count', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'top_courses' => $topCourses,
            'top_educators' => $topEducators,
            'top_posts' => $topPosts,
            'top_live_classes' => $topLiveClasses,
            'topic_engagement' => $topicEngagement
        ]);
    }

    /**
     * Get user activity metrics (messages, posts, etc)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserActivity()
    {
        if (!$this->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        // Messages per day for the last 30 days
        $messageActivity = Message::where('created_at', '>=', Carbon::now()->subDays(30))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as message_count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Posts per day for the last 30 days
        $postActivity = Post::where('created_at', '>=', Carbon::now()->subDays(30))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as post_count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Live classes per day for the last 30 days
        $liveClassActivity = LiveClass::where('created_at', '>=', Carbon::now()->subDays(30))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as class_count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // New enrollments per day for the last 30 days
        $enrollmentActivity = CourseEnrollment::where('created_at', '>=', Carbon::now()->subDays(30))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as enrollment_count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // User retention stats (users active in the last 7, 14, 30 days)
        $totalUsers = User::count();
        $activeUsers7Days = User::where('created_at', '<=', Carbon::now()->subDays(7))
            ->where(function($query) {
                $query->whereHas('posts', function($q) {
                    $q->where('created_at', '>=', Carbon::now()->subDays(7));
                })
                ->orWhereHas('comments', function($q) {
                    $q->where('created_at', '>=', Carbon::now()->subDays(7));
                })
                ->orWhereHas('messages', function($q) {
                    $q->where('created_at', '>=', Carbon::now()->subDays(7));
                });
            })
            ->count();
            
        $activeUsers14Days = User::where('created_at', '<=', Carbon::now()->subDays(14))
            ->where(function($query) {
                $query->whereHas('posts', function($q) {
                    $q->where('created_at', '>=', Carbon::now()->subDays(14));
                })
                ->orWhereHas('comments', function($q) {
                    $q->where('created_at', '>=', Carbon::now()->subDays(14));
                })
                ->orWhereHas('messages', function($q) {
                    $q->where('created_at', '>=', Carbon::now()->subDays(14));
                });
            })
            ->count();
            
        $activeUsers30Days = User::where('created_at', '<=', Carbon::now()->subDays(30))
            ->where(function($query) {
                $query->whereHas('posts', function($q) {
                    $q->where('created_at', '>=', Carbon::now()->subDays(30));
                })
                ->orWhereHas('comments', function($q) {
                    $q->where('created_at', '>=', Carbon::now()->subDays(30));
                })
                ->orWhereHas('messages', function($q) {
                    $q->where('created_at', '>=', Carbon::now()->subDays(30));
                });
            })
            ->count();

        return response()->json([
            'message_activity' => $messageActivity,
            'post_activity' => $postActivity,
            'live_class_activity' => $liveClassActivity,
            'enrollment_activity' => $enrollmentActivity,
            'retention' => [
                'total_users' => $totalUsers,
                'active_7_days' => $activeUsers7Days,
                'active_14_days' => $activeUsers14Days,
                'active_30_days' => $activeUsers30Days,
                'retention_rate_7_days' => $totalUsers > 0 ? ($activeUsers7Days / $totalUsers) * 100 : 0,
                'retention_rate_14_days' => $totalUsers > 0 ? ($activeUsers14Days / $totalUsers) * 100 : 0,
                'retention_rate_30_days' => $totalUsers > 0 ? ($activeUsers30Days / $totalUsers) * 100 : 0,
            ]
        ]);
    }

    /**
     * Get course performance metrics
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCoursePerformance()
    {
        if (!$this->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        // Course completion rates
        $courses = Course::withCount(['enrollments', 'completedEnrollments', 'inProgressEnrollments'])
            ->having('enrollments_count', '>', 0)
            ->get()
            ->map(function($course) {
                $course->completion_rate = $course->enrollments_count > 0 
                    ? ($course->completed_enrollments_count / $course->enrollments_count) * 100 
                    : 0;
                return $course;
            })
            ->sortByDesc('completion_rate')
            ->values();

        // Top performing educators by course enrollments
        $topEducators = User::where('role', User::ROLE_EDUCATOR)
            ->withCount(['courses', 'courseEnrollments'])
            ->having('courses_count', '>', 0)
            ->orderBy('course_enrollments_count', 'desc')
            ->limit(10)
            ->get(['id', 'username', 'first_name', 'last_name', 'courses_count', 'course_enrollments_count']);

        // Enrollment growth over time (last 12 months)
        $enrollmentGrowth = CourseEnrollment::where('created_at', '>=', Carbon::now()->subMonths(12))
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'courses' => $courses->take(20),
            'top_educators' => $topEducators,
            'enrollment_growth' => $enrollmentGrowth,
            'total_courses' => Course::count(),
            'total_enrollments' => CourseEnrollment::count(),
            'average_course_price' => Course::where('price', '>', 0)->avg('price') ?? 0,
            'free_courses_count' => Course::where('price', 0)->count(),
            'paid_courses_count' => Course::where('price', '>', 0)->count(),
        ]);
    }
    
    /**
     * Get subscription metrics
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSubscriptionMetrics()
    {
        if (!$this->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        
        // Current active subscriptions
        $activeSubscriptions = Subscription::where('is_active', true)
            ->where('expires_at', '>', now())
            ->count();
            
        // Subscription by plan
        $subscriptionsByPlan = Subscription::where('is_active', true)
            ->where('expires_at', '>', now())
            ->select('plan_id', DB::raw('count(*) as count'))
            ->groupBy('plan_id')
            ->get();
            
        // New subscriptions by month (last 12 months)
        $newSubscriptions = Subscription::where('created_at', '>=', Carbon::now()->subMonths(12))
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();
            
        // Churn rate (subscriptions not renewed)
        $expiredLastMonth = Subscription::where('expires_at', '<', now())
            ->where('expires_at', '>=', now()->subMonth())
            ->count();
            
        $activeLastMonth = Subscription::where('created_at', '<', now()->subMonth())
            ->where('is_active', true)
            ->where('expires_at', '>=', now()->subMonth())
            ->count();
            
        $churnRate = $activeLastMonth > 0 ? ($expiredLastMonth / $activeLastMonth) * 100 : 0;
        
        // Average subscription length
        $avgDuration = Subscription::where('expires_at', '<', now())
            ->select(DB::raw('AVG(DATEDIFF(expires_at, created_at)) as avg_days'))
            ->first();
            
        return response()->json([
            'active_subscriptions' => $activeSubscriptions,
            'subscriptions_by_plan' => $subscriptionsByPlan,
            'new_subscriptions' => $newSubscriptions,
            'churn_rate' => $churnRate,
            'average_subscription_length_days' => $avgDuration ? $avgDuration->avg_days : 0,
            'total_revenue' => Payment::where('payment_type', 'subscription')
                ->where('status', 'successful')
                ->sum('amount'),
            'revenue_this_month' => Payment::where('payment_type', 'subscription')
                ->where('status', 'successful')
                ->where('created_at', '>=', Carbon::now()->startOfMonth())
                ->sum('amount'),
        ]);
    }
    
    /**
     * Process a refund for a payment
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function processRefund(Request $request)
    {
        if (!$this->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        
        $request->validate([
            'transaction_reference' => 'required|string',
            'reason' => 'nullable|string|max:500',
            'amount' => 'nullable|numeric|min:0',
        ]);
        
        // Find the payment log
        $paymentLog = \App\Models\PaymentLog::where('transaction_reference', $request->transaction_reference)
            ->where('status', 'success')
            ->first();
            
        if (!$paymentLog) {
            return response()->json([
                'message' => 'Payment not found or not in a refundable state'
            ], 404);
        }
        
        try {
            // Get PaystackService
            $paystackService = app(\App\Services\PaystackService::class);
            
            // Initialize refund in Paystack
            $refundResult = $paystackService->refundTransaction(
                $request->transaction_reference, 
                $request->reason
            );
            
            if (!$refundResult['success']) {
                return response()->json([
                    'message' => 'Refund failed',
                    'error' => $refundResult['message']
                ], 500);
            }
            
            DB::beginTransaction();
            
            // Update payment log status
            $paymentLog->status = 'refunded';
            $paymentLog->response_data = array_merge($paymentLog->response_data ?? [], [
                'refund' => $refundResult['data']
            ]);
            $paymentLog->save();
            
            // If this was a split payment, update the splits
            if (isset($paymentLog->metadata) && is_string($paymentLog->metadata)) {
                $metadata = json_decode($paymentLog->metadata, true);
                if (isset($metadata['is_split']) && $metadata['is_split']) {
                    // Update all related payment splits
                    \App\Models\PaymentSplit::where('payment_log_id', $paymentLog->id)
                        ->update([
                            'status' => 'refunded'
                        ]);
                }
            }
            
            // Handle different payment types
            if ($paymentLog->payment_type === 'course_enrollment') {
                // Find enrollment by transaction reference
                $enrollment = \App\Models\CourseEnrollment::where('transaction_reference', $request->transaction_reference)
                    ->first();
                    
                if ($enrollment) {
                    // Update enrollment status
                    $enrollment->status = 'refunded';
                    $enrollment->save();
                    
                    // Notify the user about the refund
                    if ($enrollment->user) {
                        // Create a notification record in the database
                        DB::table('notifications')->insert([
                            'type' => 'App\\Notifications\\PaymentRefundedNotification',
                            'notifiable_type' => \App\Models\User::class,
                            'notifiable_id' => $enrollment->user_id,
                            'data' => json_encode([
                                'title' => 'Course Enrollment Refunded',
                                'body' => 'Your payment for course enrollment has been refunded.',
                                'data' => [
                                    'course_id' => $enrollment->course_id,
                                    'transaction_reference' => $request->transaction_reference,
                                    'amount' => $paymentLog->amount,
                                    'reason' => $request->reason ?? 'Admin initiated refund'
                                ],
                                'type' => 'enrollment_refunded',
                            ]),
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }
            } 
            else if ($paymentLog->payment_type === 'tutoring') {
                // Find hire session by payment reference
                $hireSession = DB::table('hire_sessions')
                    ->where('payment_reference', $request->transaction_reference)
                    ->first();
                    
                if ($hireSession) {
                    // Update hire session status
                    DB::table('hire_sessions')
                        ->where('id', $hireSession->id)
                        ->update([
                            'status' => 'refunded',
                            'updated_at' => now()
                        ]);
                    
                    // Notify both the learner and educator
                    if ($hireSession->learner_id) {
                        // Create a notification record for learner
                        DB::table('notifications')->insert([
                            'type' => 'App\\Notifications\\PaymentRefundedNotification',
                            'notifiable_type' => \App\Models\User::class,
                            'notifiable_id' => $hireSession->learner_id,
                            'data' => json_encode([
                                'title' => 'Tutoring Session Refunded',
                                'body' => 'Your payment for tutoring session has been refunded.',
                                'data' => [
                                    'hire_session_id' => $hireSession->id,
                                    'transaction_reference' => $request->transaction_reference,
                                    'amount' => $paymentLog->amount,
                                    'reason' => $request->reason ?? 'Admin initiated refund'
                                ],
                                'type' => 'tutoring_refunded',
                            ]),
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                    
                    if ($hireSession->educator_id) {
                        // Create a notification record for educator
                        DB::table('notifications')->insert([
                            'type' => 'App\\Notifications\\PaymentRefundedNotification',
                            'notifiable_type' => \App\Models\User::class,
                            'notifiable_id' => $hireSession->educator_id,
                            'data' => json_encode([
                                'title' => 'Tutoring Session Refunded',
                                'body' => 'A payment for your tutoring session has been refunded.',
                                'data' => [
                                    'hire_session_id' => $hireSession->id,
                                    'transaction_reference' => $request->transaction_reference,
                                    'amount' => $paymentLog->amount,
                                    'reason' => $request->reason ?? 'Admin initiated refund'
                                ],
                                'type' => 'tutoring_refunded',
                            ]),
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }
            }
            else if ($paymentLog->payment_type === 'subscription') {
                // Find subscription by reference
                $subscription = Subscription::where('paystack_reference', $request->transaction_reference)
                    ->first();
                    
                if ($subscription) {
                    // Update subscription status
                    $subscription->status = 'refunded';
                    $subscription->is_active = false;
                    $subscription->save();
                    
                    // Notify the user
                    if ($subscription->user_id) {
                        // Create a notification record
                        DB::table('notifications')->insert([
                            'type' => 'App\\Notifications\\PaymentRefundedNotification',
                            'notifiable_type' => \App\Models\User::class,
                            'notifiable_id' => $subscription->user_id,
                            'data' => json_encode([
                                'title' => 'Subscription Payment Refunded',
                                'body' => 'Your subscription payment has been refunded.',
                                'data' => [
                                    'subscription_id' => $subscription->id,
                                    'transaction_reference' => $request->transaction_reference,
                                    'amount' => $paymentLog->amount,
                                    'reason' => $request->reason ?? 'Admin initiated refund'
                                ],
                                'type' => 'subscription_refunded',
                            ]),
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }
            }
            
            // Create a refund record
            DB::table('refunds')->insert([
                'payment_log_id' => $paymentLog->id,
                'transaction_reference' => $request->transaction_reference,
                'reason' => $request->reason,
                'amount' => $request->has('amount') ? $request->amount : $paymentLog->amount,
                'status' => 'processed',
                'processor' => 'admin',
                'processor_id' => Auth::id(),
                'refunded_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            DB::commit();
            
            return response()->json([
                'message' => 'Refund processed successfully',
                'refund_details' => $refundResult['data']['data']
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Refund processing failed: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'An error occurred while processing the refund',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get all refunds
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRefunds(Request $request)
    {
        if (!$this->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        
        $query = DB::table('refunds')
            ->join('payment_logs', 'refunds.payment_log_id', '=', 'payment_logs.id')
            ->join('users', 'payment_logs.user_id', '=', 'users.id')
            ->select(
                'refunds.*',
                'payment_logs.payment_type',
                'payment_logs.status as payment_status',
                'payment_logs.amount as payment_amount',
                'users.first_name',
                'users.last_name',
                'users.email'
            );
            
        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('refunds.status', $request->status);
        }
        
        // Filter by payment type if provided
        if ($request->has('payment_type')) {
            $query->where('payment_logs.payment_type', $request->payment_type);
        }
        
        // Filter by date range if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('refunds.created_at', [$request->start_date, $request->end_date]);
        }
        
        $refunds = $query->orderBy('refunds.created_at', 'desc')
            ->paginate($request->per_page ?? 15);
            
        return response()->json($refunds);
    }
    
    /**
     * Get refund details
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRefundDetails($id)
    {
        if (!$this->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        
        $refund = DB::table('refunds')
            ->join('payment_logs', 'refunds.payment_log_id', '=', 'payment_logs.id')
            ->join('users', 'payment_logs.user_id', '=', 'users.id')
            ->select(
                'refunds.*',
                'payment_logs.payment_type',
                'payment_logs.status as payment_status',
                'payment_logs.amount as payment_amount',
                'payment_logs.course_id',
                'payment_logs.response_data',
                'payment_logs.metadata',
                'users.id as user_id',
                'users.first_name',
                'users.last_name',
                'users.email'
            )
            ->where('refunds.id', $id)
            ->first();
            
        if (!$refund) {
            return response()->json([
                'message' => 'Refund not found'
            ], 404);
        }
        
        // Get additional details based on payment type
        $additionalData = [];
        
        if ($refund->payment_type === 'course_enrollment' && $refund->course_id) {
            $course = \App\Models\Course::find($refund->course_id);
            if ($course) {
                $additionalData['course'] = [
                    'id' => $course->id,
                    'title' => $course->title,
                    'price' => $course->price,
                    'instructor' => [
                        'id' => $course->user_id,
                        'name' => $course->user ? $course->user->first_name . ' ' . $course->user->last_name : 'Unknown'
                    ]
                ];
            }
        }
        else if ($refund->payment_type === 'tutoring') {
            $hireSession = DB::table('hire_sessions')
                ->where('payment_reference', $refund->transaction_reference)
                ->first();
                
            if ($hireSession) {
                $educator = \App\Models\User::find($hireSession->educator_id);
                $additionalData['tutoring_session'] = [
                    'id' => $hireSession->id,
                    'status' => $hireSession->status,
                    'hours' => $hireSession->hours,
                    'amount' => $hireSession->payment_amount,
                    'educator' => $educator ? [
                        'id' => $educator->id,
                        'name' => $educator->first_name . ' ' . $educator->last_name
                    ] : null
                ];
            }
        }
        else if ($refund->payment_type === 'subscription') {
            $subscription = Subscription::where('paystack_reference', $refund->transaction_reference)
                ->first();
                
            if ($subscription) {
                $additionalData['subscription'] = [
                    'id' => $subscription->id,
                    'plan_type' => $subscription->plan_type,
                    'status' => $subscription->status,
                    'amount' => $subscription->amount,
                    'starts_at' => $subscription->starts_at,
                    'expires_at' => $subscription->expires_at
                ];
            }
        }
        
        // If this was a split payment, get split details
        if ($refund->metadata && is_string($refund->metadata)) {
            $metadata = json_decode($refund->metadata, true);
            if (isset($metadata['is_split']) && $metadata['is_split']) {
                $splits = \App\Models\PaymentSplit::where('payment_log_id', $refund->payment_log_id)->get();
                $additionalData['splits'] = $splits;
            }
        }
        
        return response()->json([
            'refund' => $refund,
            'additional_data' => $additionalData
        ]);
    }
}