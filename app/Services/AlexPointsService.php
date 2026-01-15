<?php

namespace App\Services;

use App\Models\AlexPointsLevel;
use App\Models\AlexPointsRule;
use App\Models\AlexPointsTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            // Self-healing: Create rule if missing for critical actions
            if ($actionType === 'add_url') {
                $rule = AlexPointsRule::create([
                    'action_type' => 'add_url',
                    'points' => 10,
                    'description' => 'Added content to a library',
                    'is_active' => true,
                    'is_one_time' => false,
                    'daily_limit' => 20,
                    'metadata' => json_encode(['category' => 'content'])
                ]);
            } else {
                return null; // No rule found for this action
            }
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
            
            $user->point_level = $newLevel->level;
            $user->save();
            
            return $newLevel;
        }
        
        return null;
    }
    
    /**
     * Get leaderboard of users with the most points
     * Enhanced version with rank, contributions, and current user context
     */
    public function getLeaderboard($limit = 20, $page = 1, $includeContributions = true, $includeCurrentUserContext = true)
    {
        $perPage = min($limit, 100); // Max 100 per page
        $offset = ($page - 1) * $perPage;
        
        // Get total count of users with points
        $totalUsers = User::where('alex_points', '>', 0)->count();
        
        // Get leaderboard users with rank calculation
        $users = User::where('alex_points', '>', 0)
            ->orderBy('alex_points', 'desc')
            ->orderBy('created_at', 'asc') // Tie-breaker: older accounts rank higher
            ->offset($offset)
            ->limit($perPage)
            ->get(['id', 'first_name', 'last_name', 'username', 'avatar', 'alex_points', 'point_level', 'created_at']);
        
        // Calculate ranks (accounting for ties)
        $previousPoints = null;
        $actualRank = $offset + 1;
        
        $leaderboard = $users->map(function ($user, $index) use (&$actualRank, &$previousPoints, $offset, $includeContributions) {
            // If points are different from previous user, update rank to current position
            if ($previousPoints !== null && $user->alex_points < $previousPoints) {
                $actualRank = $offset + $index + 1;
            } elseif ($previousPoints === null) {
                // First user in the list
                $actualRank = $offset + 1;
            }
            // If points are the same as previous, keep the same rank (ties)
            
            $previousPoints = $user->alex_points;
            
            $userData = [
                'rank' => $actualRank,
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                    'avatar' => $user->avatar,
                    'alex_points' => $user->alex_points,
                    'alex_level' => $user->point_level ?? 1,
                    'level_name' => $this->getLevelName($user->point_level ?? 1)
                ]
            ];
            
            // Include contribution metrics if requested
            if ($includeContributions) {
                $userData['contributions'] = $this->getUserContributions($user->id);
            }
            
            return $userData;
        });
        
        // Get current user's rank and context if requested
        $currentUserData = null;
        if ($includeCurrentUserContext && auth()->check()) {
            $currentUser = auth()->user();
            $currentUserRank = $this->getUserRank($currentUser->id);
            
            if ($currentUserRank) {
                $currentUserData = [
                    'rank' => $currentUserRank['rank'],
                    'user' => [
                        'id' => $currentUser->id,
                        'username' => $currentUser->username,
                        'name' => trim(($currentUser->first_name ?? '') . ' ' . ($currentUser->last_name ?? '')),
                        'avatar' => $currentUser->avatar,
                        'alex_points' => $currentUser->alex_points,
                        'alex_level' => $currentUser->point_level ?? 1,
                        'level_name' => $this->getLevelName($currentUser->point_level ?? 1)
                    ],
                    'context' => [
                        'users_above' => max(0, $currentUserRank['rank'] - 1),
                        'users_below' => max(0, $totalUsers - $currentUserRank['rank'])
                    ]
                ];
                
                if ($includeContributions) {
                    $currentUserData['contributions'] = $this->getUserContributions($currentUser->id);
                }
            }
        }
        
        return [
            'leaderboard' => $leaderboard->values(),
            'current_user' => $currentUserData,
            'pagination' => [
                'current_page' => (int) $page,
                'per_page' => (int) $perPage,
                'total_users' => (int) $totalUsers,
                'total_pages' => (int) ceil($totalUsers / $perPage),
                'has_more' => ($offset + $perPage) < $totalUsers
            ]
        ];
    }
    
    /**
     * Get user's rank in the leaderboard
     */
    private function getUserRank($userId)
    {
        $user = User::find($userId);
        if (!$user || $user->alex_points <= 0) {
            return null;
        }
        
        // Count users with more points, or same points but created earlier
        $rank = User::where(function($query) use ($user) {
            $query->where('alex_points', '>', $user->alex_points)
                  ->orWhere(function($q) use ($user) {
                      $q->where('alex_points', $user->alex_points)
                        ->where('created_at', '<', $user->created_at);
                  });
        })->count() + 1;
        
        return ['rank' => $rank];
    }
    
    /**
     * Get user's contribution metrics
     */
    private function getUserContributions($userId)
    {
        // Check if tables exist before querying
        $postsCount = 0;
        $commentsCount = 0;
        
        if (Schema::hasTable('posts')) {
            $postsCount = DB::table('posts')->where('user_id', $userId)->count();
        }
        
        if (Schema::hasTable('comments')) {
            $commentsCount = DB::table('comments')->where('user_id', $userId)->count();
        }
        
        return [
            'posts_created' => $postsCount,
            'libraries_created' => DB::table('open_libraries')->where('creator_id', $userId)->count(),
            'urls_added' => DB::table('library_urls')->where('created_by', $userId)->count(),
            'comments_made' => $commentsCount
        ];
    }
    
    /**
     * Get level name from level number
     */
    private function getLevelName($level)
    {
        $levelModel = AlexPointsLevel::where('level', $level)->first();
        return $levelModel ? $levelModel->name : 'Basic';
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