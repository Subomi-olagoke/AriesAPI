<?php

namespace App\Services;

use App\Models\AlexPointsLevel;
use App\Models\AlexPointsRule;
use App\Models\AlexPointsTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AlexPointsService
{
    /**
     * Add points to a user based on an action type
     */
    public function addPoints(User $user, string $actionType, $referenceType = null, $referenceId = null, $description = null, $metadata = null)
    {
        // Find the points rule for this action
        $rule = AlexPointsRule::where('action_type', $actionType)
            ->where('is_active', true)
            ->first();
            
        if (!$rule) {
            return null; // No rule found for this action
        }
        
        // Check one-time rule
        if ($rule->is_one_time) {
            $exists = AlexPointsTransaction::where('user_id', $user->id)
                ->where('action_type', $actionType)
                ->exists();
                
            if ($exists) {
                return null; // User already performed this one-time action
            }
        }
        
        // Check daily limit
        if ($rule->daily_limit > 0) {
            $todayCount = AlexPointsTransaction::where('user_id', $user->id)
                ->where('action_type', $actionType)
                ->whereDate('created_at', now()->toDateString())
                ->count();
                
            if ($todayCount >= $rule->daily_limit) {
                return null; // Daily limit reached
            }
        }
        
        // Create transaction and add points
        return $user->addPoints(
            $rule->points,
            $actionType,
            $referenceType,
            $referenceId,
            $description ?? $rule->description,
            $metadata
        );
    }
    
    /**
     * Get a user's current level
     */
    public function getUserLevel(User $user)
    {
        return AlexPointsLevel::where('points_required', '<=', $user->alex_points)
            ->orderBy('points_required', 'desc')
            ->first();
    }
    
    /**
     * Get all levels with user progression data
     */
    public function getLevelsWithProgress(User $user)
    {
        $levels = AlexPointsLevel::orderBy('points_required', 'asc')->get();
        $currentLevel = $this->getUserLevel($user);
        
        foreach ($levels as $level) {
            $level->is_current = ($currentLevel && $level->id == $currentLevel->id);
            $level->is_achieved = ($level->points_required <= $user->alex_points);
            
            if ($level->points_required > $user->alex_points) {
                $level->progress_percentage = 0;
                
                // Calculate progress to this level from the previous level
                $previousLevel = AlexPointsLevel::where('points_required', '<', $level->points_required)
                    ->orderBy('points_required', 'desc')
                    ->first();
                    
                $startingPoints = $previousLevel ? $previousLevel->points_required : 0;
                $pointsNeeded = $level->points_required - $startingPoints;
                $userProgress = $user->alex_points - $startingPoints;
                
                if ($pointsNeeded > 0 && $userProgress > 0) {
                    $level->progress_percentage = min(round(($userProgress / $pointsNeeded) * 100), 99);
                }
            } else {
                $level->progress_percentage = 100;
            }
        }
        
        return $levels;
    }
    
    /**
     * Get the next level a user can achieve
     */
    public function getNextLevel(User $user)
    {
        return AlexPointsLevel::where('points_required', '>', $user->alex_points)
            ->orderBy('points_required', 'asc')
            ->first();
    }
    
    /**
     * Get points needed for the next level
     */
    public function getPointsToNextLevel(User $user)
    {
        $nextLevel = $this->getNextLevel($user);
        return $nextLevel ? $nextLevel->points_required - $user->alex_points : 0;
    }
    
    /**
     * Award points for a specific user action
     */
    public function awardPointsForAction(User $user, string $actionType, $referenceType = null, $referenceId = null, $description = null, $metadata = null)
    {
        return DB::transaction(function () use ($user, $actionType, $referenceType, $referenceId, $description, $metadata) {
            return $this->addPoints($user, $actionType, $referenceType, $referenceId, $description, $metadata);
        });
    }
    
    /**
     * Check if a user should level up and process the level up
     */
    public function processLevelUp(User $user)
    {
        $currentLevel = $this->getUserLevel($user);
        $newLevel = AlexPointsLevel::where('points_required', '<=', $user->alex_points)
            ->orderBy('points_required', 'desc')
            ->first();
            
        if (!$currentLevel || ($newLevel && $newLevel->level > $currentLevel->level)) {
            // User leveled up, create a level up transaction
            $user->addPoints(
                0, // No additional points for leveling up
                'level_up',
                'level',
                $newLevel->id,
                "Leveled up to {$newLevel->name}",
                json_encode(['previous_level' => $currentLevel ? $currentLevel->level : 0])
            );
            
            $user->alex_level = $newLevel->level;
            $user->save();
            
            return $newLevel;
        }
        
        return null;
    }
    
    /**
     * Get leaderboard of users with the most points
     */
    public function getLeaderboard($limit = 10)
    {
        return User::where('alex_points', '>', 0)
            ->orderBy('alex_points', 'desc')
            ->limit($limit)
            ->get(['id', 'name', 'username', 'alex_points', 'alex_level']);
    }
}