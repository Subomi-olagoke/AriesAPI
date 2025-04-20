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
     * Get the authenticated user's points summary
     */
    public function summary()
    {
        $user = Auth::user();
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
     */
    public function leaderboard(Request $request)
    {
        $limit = $request->limit ?? 10;
        $leaderboard = $this->pointsService->getLeaderboard($limit);
        
        return response()->json($leaderboard);
    }
}