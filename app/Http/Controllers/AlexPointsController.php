<?php

namespace App\Http\Controllers;

use App\Models\AlexPointsLevel;
use App\Models\AlexPointsRule;
use App\Models\AlexPointsTransaction;
use App\Models\User;
use App\Services\AlexPointsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AlexPointsController extends Controller
{
    protected $pointsService;
    
    public function __construct(AlexPointsService $pointsService)
    {
        $this->pointsService = $pointsService;
    }
    
    /**
     * Get a user's points summary
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function summary(Request $request)
    {
        $currentUser = Auth::user();
        $targetUserId = $request->query('user_id');
        
        // If user_id is provided, fetch that user's summary
        // Otherwise, fetch the authenticated user's summary
        if ($targetUserId) {
            $targetUser = User::where('username', $targetUserId)
                ->orWhere('id', $targetUserId)
                ->first();
            
            if (!$targetUser) {
                return response()->json([
                    'message' => 'User not found'
                ], 404);
            }
            
            $user = $targetUser;
        } else {
            if (!$currentUser) {
                return response()->json([
                    'message' => 'Authentication required'
                ], 401);
            }
            $user = $currentUser;
        }
        
        $currentLevel = $this->pointsService->getUserLevel($user);
        $nextLevel = $this->pointsService->getNextLevel($user);
        $pointsToNextLevel = $this->pointsService->getPointsToNextLevel($user);

        return response()->json([
            'points' => $user->alex_points,
            'current_level' => $currentLevel,
            'next_level' => $nextLevel,
            'points_to_next_level' => $pointsToNextLevel,
        ]);
    }

    /**
     * Get the authenticated user's points transactions
     */
    public function transactions(Request $request)
    {
        $user = Auth::user();
        $transactions = $user->pointsTransactions()
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($transactions);
    }

    /**
     * Get all available points rules
     */
    public function rules()
    {
        $rules = AlexPointsRule::where('is_active', true)->get();
        return response()->json($rules);
    }

    /**
     * Get all points levels
     */
    public function levels()
    {
        $levels = AlexPointsLevel::orderBy('points_required', 'asc')->get();
        return response()->json($levels);
    }

    /**
     * Admin: Create a new points rule
     */
    public function createRule(Request $request)
    {
        $request->validate([
            'action_type' => 'required|string|max:255',
            'points' => 'required|integer',
            'description' => 'required|string',
            'is_active' => 'boolean',
            'is_one_time' => 'boolean',
            'daily_limit' => 'nullable|integer',
            'metadata' => 'nullable|json',
        ]);

        $rule = AlexPointsRule::create($request->all());
        return response()->json($rule, 201);
    }

    /**
     * Admin: Update a points rule
     */
    public function updateRule(Request $request, $id)
    {
        $rule = AlexPointsRule::findOrFail($id);

        $request->validate([
            'action_type' => 'string|max:255',
            'points' => 'integer',
            'description' => 'string',
            'is_active' => 'boolean',
            'is_one_time' => 'boolean',
            'daily_limit' => 'nullable|integer',
            'metadata' => 'nullable|json',
        ]);

        $rule->update($request->all());
        return response()->json($rule);
    }

    /**
     * Admin: Create a new points level
     */
    public function createLevel(Request $request)
    {
        $request->validate([
            'level' => 'required|integer|unique:alex_points_levels',
            'points_required' => 'required|integer|unique:alex_points_levels',
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'rewards' => 'nullable|json',
        ]);

        $level = AlexPointsLevel::create($request->all());
        return response()->json($level, 201);
    }

    /**
     * Admin: Update a points level
     */
    public function updateLevel(Request $request, $id)
    {
        $level = AlexPointsLevel::findOrFail($id);

        $request->validate([
            'level' => 'integer|unique:alex_points_levels,level,' . $id,
            'points_required' => 'integer|unique:alex_points_levels,points_required,' . $id,
            'name' => 'string|max:255',
            'description' => 'string',
            'rewards' => 'nullable|json',
        ]);

        $level->update($request->all());
        return response()->json($level);
    }

    /**
     * Admin: Manually adjust a user's points
     */
    public function adjustPoints(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'points' => 'required|integer',
            'action_type' => 'required|string|max:255',
            'description' => 'required|string',
            'metadata' => 'nullable|json',
        ]);

        $user = User::findOrFail($request->user_id);
        $transaction = $user->addPoints(
            $request->points,
            $request->action_type,
            'manual_adjustment',
            null,
            $request->description,
            $request->metadata
        );

        return response()->json($transaction, 201);
    }
    
    /**
     * Get leaderboard of users with the most points
     * Enhanced with rank, contributions, and current user context
     */
    public function leaderboard(Request $request)
    {
        $limit = min($request->input('limit', 20), 100); // Default 20, max 100
        $page = max(1, $request->input('page', 1));
        $includeContributions = $request->input('include_contributions', true);
        $includeCurrentUserContext = $request->input('include_current_user_context', true);
        
        $leaderboard = $this->pointsService->getLeaderboard(
            $limit,
            $page,
            filter_var($includeContributions, FILTER_VALIDATE_BOOLEAN),
            filter_var($includeCurrentUserContext, FILTER_VALIDATE_BOOLEAN)
        );
        
        return response()->json($leaderboard);
    }
}