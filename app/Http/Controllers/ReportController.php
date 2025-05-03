<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\User;
use App\Models\Post;
use App\Models\Educators;
use App\Notifications\ReportSubmittedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class ReportController extends Controller
{
    /**
     * Store a new report
     *
     * @param Request $request
     * @param string $type Type of report (user, post, educator)
     * @param int|string $id ID of the reported item
     * @return \Illuminate\Http\JsonResponse
     */
    private function storeReport(Request $request, $type, $id)
    {
        $reporter = Auth::user();
        
        // Validate request
        $request->validate([
            'reason' => 'required|string|max:1000',
            'notes' => 'nullable|string|max:2000',
        ]);

        // Determine reportable type and check if it exists
        switch($type) {
            case 'user':
                $reportableType = User::class;
                $reportable = User::findOrFail($id);
                break;
            case 'post':
                $reportableType = Post::class;
                $reportable = Post::findOrFail($id);
                break;
            case 'educator':
                $reportableType = User::class;
                $reportable = User::where('id', $id)
                    ->where('role', User::ROLE_EDUCATOR)
                    ->firstOrFail();
                break;
            default:
                return response()->json([
                    'message' => 'Invalid report type'
                ], 400);
        }

        // Prevent self-reporting
        if ($type === 'user' || $type === 'educator') {
            if ($reportable->id === $reporter->id) {
                return response()->json([
                    'message' => 'You cannot report yourself'
                ], 400);
            }
        }

        // Check if a similar report exists from this user
        $existingReport = Report::where('reporter_id', $reporter->id)
            ->where('reportable_type', $reportableType)
            ->where('reportable_id', $reportable->id)
            ->where('created_at', '>', now()->subDays(7))
            ->first();

        if ($existingReport) {
            return response()->json([
                'message' => 'You have already reported this ' . $type . ' recently',
                'report' => $existingReport
            ], 409);
        }

        // Create the report
        $report = new Report();
        $report->reporter_id = $reporter->id;
        $report->reportable_type = $reportableType;
        $report->reportable_id = $reportable->id;
        $report->reason = $request->reason;
        $report->notes = $request->notes;
        $report->status = 'pending';
        
        if ($report->save()) {
            // Notify admins about the report
            // Get all admin users
            $admins = User::where('isAdmin', true)->get();
            
            // Send notification to all admins
            Notification::send($admins, new ReportSubmittedNotification($report));
            
            return response()->json([
                'message' => 'Report submitted successfully',
                'report' => $report
            ], 201);
        }

        return response()->json([
            'message' => 'Failed to submit report'
        ], 500);
    }

    /**
     * Report a user
     *
     * @param Request $request
     * @param string|int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function reportUser(Request $request, $userId)
    {
        return $this->storeReport($request, 'user', $userId);
    }

    /**
     * Report a post
     *
     * @param Request $request
     * @param int $postId
     * @return \Illuminate\Http\JsonResponse
     */
    public function reportPost(Request $request, $postId)
    {
        return $this->storeReport($request, 'post', $postId);
    }

    /**
     * Report an educator
     *
     * @param Request $request
     * @param string|int $educatorId
     * @return \Illuminate\Http\JsonResponse
     */
    public function reportEducator(Request $request, $educatorId)
    {
        return $this->storeReport($request, 'educator', $educatorId);
    }

    /**
     * Get all reports for admins
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Only admins can view all reports
        if (!Auth::user()->isAdmin) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $query = Report::with(['reporter', 'reportable']);
        
        // Filter by status if provided
        if ($request->has('status') && in_array($request->status, ['pending', 'reviewed', 'resolved', 'dismissed'])) {
            $query->where('status', $request->status);
        }
        
        // Filter by type if provided
        if ($request->has('type')) {
            switch ($request->type) {
                case 'user':
                    $query->where('reportable_type', User::class);
                    break;
                case 'post':
                    $query->where('reportable_type', Post::class);
                    break;
                case 'educator':
                    $query->where('reportable_type', User::class)
                        ->whereHas('reportable', function($q) {
                            $q->where('role', User::ROLE_EDUCATOR);
                        });
                    break;
            }
        }
        
        // Paginate the results
        $reports = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);
        
        return response()->json($reports);
    }

    /**
     * Get a specific report by ID (admin only)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        // Only admins can view report details
        if (!Auth::user()->isAdmin) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $report = Report::with(['reporter', 'reportable'])->findOrFail($id);
        
        return response()->json($report);
    }

    /**
     * Update a report's status (admin only)
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, $id)
    {
        // Only admins can update report status
        if (!Auth::user()->isAdmin) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'status' => 'required|string|in:pending,reviewed,resolved,dismissed',
            'notes' => 'nullable|string|max:2000',
        ]);

        $report = Report::findOrFail($id);
        $report->status = $request->status;
        
        if ($request->has('notes')) {
            $report->notes = $request->notes;
        }
        
        if ($report->save()) {
            return response()->json([
                'message' => 'Report status updated successfully',
                'report' => $report
            ]);
        }

        return response()->json([
            'message' => 'Failed to update report status'
        ], 500);
    }

    /**
     * Get reports made by the authenticated user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function myReports()
    {
        $reports = Auth::user()->reports()
            ->with('reportable')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json($reports);
    }
}