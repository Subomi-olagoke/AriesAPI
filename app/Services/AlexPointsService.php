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
     * Spend points to purchase something
     * 
     * @param User $user The user who is spending points
     * @param int $points Number of points to spend
     * @param string $purposeType Type of purchase (e.g., 'course_enrollment', 'hire_educator')
     * @param string $purposeId ID of the purchased item (course_id, hire_request_id, etc.)
     * @param string|null $description Description of the transaction
     * @param array|null $metadata Additional metadata about the transaction
     * @return AlexPointsTransaction|false Returns the transaction or false if user doesn't have enough points
     */
    public function spendPoints(User $user, int $points, string $purposeType, string $purposeId, string $description = null, array $metadata = null)
    {
        // Check if user has enough points
        if ($user->alex_points < $points) {
            return false;
        }
        
        // Create transaction and deduct points
        return $user->deductPoints(
            $points,
            'purchase',
            $purposeType,
            $purposeId,
            $description ?? "Spent points for {$purposeType}",
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
    
    /**
     * Convert a currency amount to points
     * 
     * @param float $amount Amount in currency
     * @param User $user User to determine conversion rate based on level
     * @return int Number of points required
     */
    public function currencyToPoints(float $amount, User $user)
    {
        // Get user's current level
        $userLevel = $this->getUserLevel($user);
        
        if (!$userLevel || !$userLevel->points_to_currency_rate) {
            // Default conversion rate: 100 points = 1 unit of currency
            $conversionRate = 100;
        } else {
            $conversionRate = $userLevel->points_to_currency_rate;
        }
        
        // Calculate points needed (round up to ensure enough points)
        return ceil($amount * $conversionRate);
    }
    
    /**
     * Convert points to currency value
     * 
     * @param int $points Number of points
     * @param User $user User to determine conversion rate based on level
     * @return float Amount in currency
     */
    public function pointsToCurrency(int $points, User $user)
    {
        // Get user's current level
        $userLevel = $this->getUserLevel($user);
        
        if (!$userLevel || !$userLevel->points_to_currency_rate) {
            // Default conversion rate: 100 points = 1 unit of currency
            $conversionRate = 100;
        } else {
            $conversionRate = $userLevel->points_to_currency_rate;
        }
        
        // Calculate currency amount
        return $points / $conversionRate;
    }
    
    /**
     * Refund points to a user
     * 
     * @param User $user User to refund points to
     * @param int $points Number of points to refund
     * @param string $purposeType Type of refund (e.g., 'course_enrollment_refund', 'hire_educator_refund')
     * @param string $purposeId ID of the refunded item
     * @param string|null $description Description of the refund
     * @param array|null $metadata Additional metadata about the refund
     * @return AlexPointsTransaction Transaction record
     */
    public function refundPoints(User $user, int $points, string $purposeType, string $purposeId, string $description = null, array $metadata = null)
    {
        return $user->addPoints(
            $points,
            'refund',
            $purposeType,
            $purposeId,
            $description ?? "Refunded points for {$purposeType}",
            $metadata
        );
    }
    
    /**
     * Check if user has enough points for a purchase
     * 
     * @param User $user User to check
     * @param int $pointsNeeded Points required for purchase
     * @return bool True if user has enough points
     */
    public function hasEnoughPoints(User $user, int $pointsNeeded)
    {
        return $user->alex_points >= $pointsNeeded;
    }
}