<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserMute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BlockController extends Controller
{
    /**
     * Block a user
     */
    public function blockUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);
        
        $user = Auth::user();
        $blockedUserId = $request->user_id;
        
        // Prevent blocking yourself
        if ($user->id === $blockedUserId) {
            return response()->json([
                'message' => 'You cannot block yourself',
            ], 400);
        }
        
        // Check if already blocked
        if ($user->hasBlocked(User::find($blockedUserId))) {
            return response()->json([
                'message' => 'User is already blocked',
            ], 409);
        }
        
        // Create block relationship
        $user->blockedUsers()->attach($blockedUserId);
        
        return response()->json([
            'message' => 'User blocked successfully',
        ], 200);
    }
    
    /**
     * Unblock a user
     */
    public function unblockUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);
        
        $user = Auth::user();
        $blockedUserId = $request->user_id;
        
        // Remove block relationship
        $user->blockedUsers()->detach($blockedUserId);
        
        return response()->json([
            'message' => 'User unblocked successfully',
        ], 200);
    }
    
    /**
     * Mute a user
     */
    public function muteUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);
        
        $user = Auth::user();
        $mutedUserId = $request->user_id;
        
        // Prevent muting yourself
        if ($user->id === $mutedUserId) {
            return response()->json([
                'message' => 'You cannot mute yourself',
            ], 400);
        }
        
        // Check if already muted
        if ($user->hasMuted(User::find($mutedUserId))) {
            return response()->json([
                'message' => 'User is already muted',
            ], 409);
        }
        
        // Create mute relationship
        $user->mutedUsers()->attach($mutedUserId);
        
        return response()->json([
            'message' => 'User muted successfully',
        ], 200);
    }
    
    /**
     * Unmute a user
     */
    public function unmuteUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);
        
        $user = Auth::user();
        $mutedUserId = $request->user_id;
        
        // Remove mute relationship
        $user->mutedUsers()->detach($mutedUserId);
        
        return response()->json([
            'message' => 'User unmuted successfully',
        ], 200);
    }
    
    /**
     * Get blocked users
     */
    public function getBlockedUsers()
    {
        $user = Auth::user();
        $blockedUsers = $user->blockedUsers()->get(['id', 'username', 'first_name', 'last_name', 'avatar']);
        
        return response()->json([
            'blocked_users' => $blockedUsers,
        ], 200);
    }
    
    /**
     * Get muted users
     */
    public function getMutedUsers()
    {
        $user = Auth::user();
        $mutedUsers = $user->mutedUsers()->get(['id', 'username', 'first_name', 'last_name', 'avatar']);
        
        return response()->json([
            'muted_users' => $mutedUsers,
        ], 200);
    }
}