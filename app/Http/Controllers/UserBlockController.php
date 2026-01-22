<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserBlock;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class UserBlockController extends Controller
{
    /**
     * Block a user
     *
     * @param Request $request
     * @param string|int $userId ID of the user to block
     * @return \Illuminate\Http\JsonResponse
     */
    public function blockUser(Request $request, $userId)
    {
        $currentUser = Auth::user();
        
        if (!$currentUser) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }
        
        // Prevent self-blocking
        if ($currentUser->id === $userId) {
            return response()->json([
                'message' => 'You cannot block yourself'
            ], 400);
        }
        
        // Check if user exists
        $userToBlock = User::find($userId);
        if (!$userToBlock) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }
        
        // Check if already blocked
        $existingBlock = UserBlock::where('user_id', $currentUser->id)
            ->where('blocked_user_id', $userId)
            ->first();
            
        if ($existingBlock) {
            return response()->json([
                'message' => 'User is already blocked',
                'blocked' => true
            ], 200);
        }
        
        // Create the block
        try {
            DB::beginTransaction();
            
            $block = UserBlock::create([
                'user_id' => $currentUser->id,
                'blocked_user_id' => $userId,
            ]);
            
            // Optionally create a report when blocking (as per Apple's requirement)
            // This notifies the developer of inappropriate content
            if ($request->has('report_reason') && $request->report_reason) {
                $report = new Report();
                $report->reporter_id = $currentUser->id;
                $report->reportable_type = User::class;
                $report->reportable_id = $userId;
                $report->reason = $request->report_reason ?: 'User blocked for inappropriate behavior';
                $report->notes = $request->has('report_notes') ? $request->report_notes : 'User was blocked by another user';
                $report->status = 'pending';
                $report->save();
                
                // Notify admins about the report
                $admins = User::where('isAdmin', true)->get();
                \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\ReportSubmittedNotification($report));
            }
            
            DB::commit();
            
            Log::info("User {$currentUser->id} blocked user {$userId}");
            
            return response()->json([
                'message' => 'User blocked successfully',
                'blocked' => true,
                'block' => $block
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error blocking user: " . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to block user',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Unblock a user
     *
     * @param Request $request
     * @param string|int $userId ID of the user to unblock
     * @return \Illuminate\Http\JsonResponse
     */
    public function unblockUser(Request $request, $userId)
    {
        $currentUser = Auth::user();
        
        if (!$currentUser) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }
        
        // Find and delete the block
        $block = UserBlock::where('user_id', $currentUser->id)
            ->where('blocked_user_id', $userId)
            ->first();
            
        if (!$block) {
            return response()->json([
                'message' => 'User is not blocked',
                'blocked' => false
            ], 404);
        }
        
        $block->delete();
        
        Log::info("User {$currentUser->id} unblocked user {$userId}");
        
        return response()->json([
            'message' => 'User unblocked successfully',
            'blocked' => false
        ], 200);
    }
    
    /**
     * Toggle block status (block if not blocked, unblock if blocked)
     *
     * @param Request $request
     * @param string|int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleBlock(Request $request, $userId)
    {
        $currentUser = Auth::user();
        
        if (!$currentUser) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }
        
        $isBlocked = UserBlock::where('user_id', $currentUser->id)
            ->where('blocked_user_id', $userId)
            ->exists();
            
        if ($isBlocked) {
            return $this->unblockUser($request, $userId);
        } else {
            return $this->blockUser($request, $userId);
        }
    }
    
    /**
     * Get list of blocked users
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBlockedUsers(Request $request)
    {
        $currentUser = Auth::user();
        
        if (!$currentUser) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }
        
        $blockedUsers = $currentUser->blockedUsers()
            ->select('users.id', 'users.username', 'users.first_name', 'users.last_name', 'users.avatar')
            ->get();
            
        return response()->json([
            'blocked_users' => $blockedUsers
        ], 200);
    }
    
    /**
     * Check if a user is blocked
     *
     * @param Request $request
     * @param string|int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkBlockStatus(Request $request, $userId)
    {
        $currentUser = Auth::user();
        
        if (!$currentUser) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }
        
        $isBlocked = UserBlock::where('user_id', $currentUser->id)
            ->where('blocked_user_id', $userId)
            ->exists();
            
        return response()->json([
            'blocked' => $isBlocked
        ], 200);
    }
}
