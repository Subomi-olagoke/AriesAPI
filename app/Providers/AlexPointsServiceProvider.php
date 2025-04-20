<?php

namespace App\Providers;

use App\Models\AlexPointsLevel;
use App\Models\AlexPointsRule;
use Illuminate\Support\ServiceProvider;

class AlexPointsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the AlexPoints service
        $this->app->bind('alexpoints', function ($app) {
            return new \App\Services\AlexPointsService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Ensure at least one level exists
        $this->ensureBasicLevelsExist();
        
        // Ensure essential rules exist
        $this->ensureBasicRulesExist();
    }
    
    /**
     * Ensure that at least one level exists for the AlexPoints system
     */
    protected function ensureBasicLevelsExist(): void
    {
        if (AlexPointsLevel::count() === 0) {
            // Create the first level
            AlexPointsLevel::create([
                'level' => 1,
                'name' => 'Newcomer',
                'points_required' => 0,
                'description' => 'Welcome to the platform! Start interacting to earn points and level up.',
                'rewards' => json_encode([
                    'badge' => 'newcomer_badge',
                    'features' => ['basic_access']
                ])
            ]);
        }
    }
    
    /**
     * Ensure that essential rules exist for the AlexPoints system
     */
    protected function ensureBasicRulesExist(): void
    {
        $essentialRules = [
            [
                'action_type' => 'user_registered',
                'points' => 100,
                'description' => 'Signed up for an account',
                'is_active' => true,
                'is_one_time' => true,
                'daily_limit' => 1,
            ],
            [
                'action_type' => 'daily_login',
                'points' => 5,
                'description' => 'Daily login bonus',
                'is_active' => true,
                'is_one_time' => false,
                'daily_limit' => 1,
            ]
        ];
        
        foreach ($essentialRules as $rule) {
            AlexPointsRule::firstOrCreate(
                ['action_type' => $rule['action_type']],
                $rule
            );
        }
    }
}