<?php

namespace App\Console\Commands;

use App\Models\AlexPointsLevel;
use App\Models\AlexPointsRule;
use Illuminate\Console\Command;

class SeedAlexPointsSystem extends Command
{
    protected $signature = 'alexpoints:seed';
    protected $description = 'Seed the initial data for the AlexPoints system';

    /**
     * Execute the command.
     */
    public function handle()
    {
        $this->info('Seeding AlexPoints rules...');
        $this->seedRules();
        
        $this->info('Seeding AlexPoints levels...');
        $this->seedLevels();
        
        $this->info('AlexPoints system seeded successfully.');
        
        return Command::SUCCESS;
    }
    
    /**
     * Seed the initial points rules.
     */
    private function seedRules()
    {
        $rules = [
            [
                'action_type' => 'user_registered',
                'points' => 100,
                'description' => 'Signed up for an account',
                'is_active' => true,
                'is_one_time' => true,
                'daily_limit' => 1,
                'metadata' => json_encode(['category' => 'onboarding'])
            ],
            [
                'action_type' => 'daily_login',
                'points' => 5,
                'description' => 'Daily login bonus',
                'is_active' => true,
                'is_one_time' => false,
                'daily_limit' => 1,
                'metadata' => json_encode(['category' => 'engagement'])
            ],
            [
                'action_type' => 'profile_completed',
                'points' => 50,
                'description' => 'Completed your profile',
                'is_active' => true,
                'is_one_time' => true,
                'daily_limit' => 1,
                'metadata' => json_encode(['category' => 'onboarding'])
            ],
            [
                'action_type' => 'create_post',
                'points' => 10,
                'description' => 'Created a post',
                'is_active' => true,
                'is_one_time' => false,
                'daily_limit' => 5,
                'metadata' => json_encode(['category' => 'content'])
            ],
            [
                'action_type' => 'receive_like',
                'points' => 2,
                'description' => 'Received a like on your content',
                'is_active' => true,
                'is_one_time' => false,
                'daily_limit' => 50,
                'metadata' => json_encode(['category' => 'social'])
            ],
            [
                'action_type' => 'comment_post',
                'points' => 5,
                'description' => 'Commented on a post',
                'is_active' => true,
                'is_one_time' => false,
                'daily_limit' => 10,
                'metadata' => json_encode(['category' => 'social'])
            ],
            [
                'action_type' => 'follow_user',
                'points' => 3,
                'description' => 'Followed another user',
                'is_active' => true,
                'is_one_time' => false,
                'daily_limit' => 10,
                'metadata' => json_encode(['category' => 'social'])
            ],
            [
                'action_type' => 'gained_follower',
                'points' => 5,
                'description' => 'Gained a new follower',
                'is_active' => true,
                'is_one_time' => false,
                'daily_limit' => 50,
                'metadata' => json_encode(['category' => 'social'])
            ],
            [
                'action_type' => 'create_course',
                'points' => 100,
                'description' => 'Created a course',
                'is_active' => true,
                'is_one_time' => false,
                'daily_limit' => 3,
                'metadata' => json_encode(['category' => 'educator'])
            ],
            [
                'action_type' => 'enroll_course',
                'points' => 20,
                'description' => 'Enrolled in a course',
                'is_active' => true,
                'is_one_time' => false,
                'daily_limit' => 5,
                'metadata' => json_encode(['category' => 'learning'])
            ],
            [
                'action_type' => 'complete_lesson',
                'points' => 15,
                'description' => 'Completed a lesson',
                'is_active' => true,
                'is_one_time' => false,
                'daily_limit' => 10,
                'metadata' => json_encode(['category' => 'learning'])
            ],
            [
                'action_type' => 'complete_course',
                'points' => 100,
                'description' => 'Completed a course',
                'is_active' => true,
                'is_one_time' => false,
                'daily_limit' => 3,
                'metadata' => json_encode(['category' => 'learning'])
            ],
            [
                'action_type' => 'host_live_class',
                'points' => 50,
                'description' => 'Hosted a live class',
                'is_active' => true,
                'is_one_time' => false,
                'daily_limit' => 3,
                'metadata' => json_encode(['category' => 'educator'])
            ],
            [
                'action_type' => 'join_live_class',
                'points' => 15,
                'description' => 'Joined a live class',
                'is_active' => true,
                'is_one_time' => false,
                'daily_limit' => 5,
                'metadata' => json_encode(['category' => 'learning'])
            ],
            [
                'action_type' => 'send_message',
                'points' => 2,
                'description' => 'Sent a message',
                'is_active' => true,
                'is_one_time' => false,
                'daily_limit' => 20,
                'metadata' => json_encode(['category' => 'social'])
            ],
            [
                'action_type' => 'send_channel_message',
                'points' => 3,
                'description' => 'Sent a message in a channel',
                'is_active' => true,
                'is_one_time' => false,
                'daily_limit' => 30,
                'metadata' => json_encode(['category' => 'social'])
            ],
            [
                'action_type' => 'send_class_message',
                'points' => 3,
                'description' => 'Sent a message in a live class',
                'is_active' => true,
                'is_one_time' => false,
                'daily_limit' => 30,
                'metadata' => json_encode(['category' => 'learning'])
            ],
            [
                'action_type' => 'create_readlist',
                'points' => 20,
                'description' => 'Created a readlist',
                'is_active' => true,
                'is_one_time' => false,
                'daily_limit' => 5,
                'metadata' => json_encode(['category' => 'content'])
            ],
            [
                'action_type' => 'add_to_readlist',
                'points' => 5,
                'description' => 'Added an item to a readlist',
                'is_active' => true,
                'is_one_time' => false,
                'daily_limit' => 10,
                'metadata' => json_encode(['category' => 'content'])
            ],
            [
                'action_type' => 'subscribe',
                'points' => 200,
                'description' => 'Subscribed to a paid plan',
                'is_active' => true,
                'is_one_time' => false,
                'daily_limit' => 1,
                'metadata' => json_encode(['category' => 'premium'])
            ],
            [
                'action_type' => 'add_url',
                'points' => 10,
                'description' => 'Added content to a library',
                'is_active' => true,
                'is_one_time' => false,
                'daily_limit' => 20,
                'metadata' => json_encode(['category' => 'content'])
            ],
            [
                'action_type' => 'create_library',
                'points' => 50,
                'description' => 'Created a new library',
                'is_active' => true,
                'is_one_time' => false,
                'daily_limit' => 3,
                'metadata' => json_encode(['category' => 'content'])
            ],
        ];
        
        foreach ($rules as $rule) {
            AlexPointsRule::updateOrCreate(
                ['action_type' => $rule['action_type']],
                $rule
            );
            
            $this->line("  - Seeded rule: {$rule['action_type']}");
        }
    }
    
    /**
     * Seed the initial level definitions.
     */
    private function seedLevels()
    {
        $levels = [
            [
                'level' => 1,
                'name' => 'Newcomer',
                'points_required' => 0,
                'description' => 'Welcome to the platform! Start interacting to earn points and level up.',
                'rewards' => json_encode([
                    'badge' => 'newcomer_badge',
                    'features' => ['basic_access']
                ])
            ],
            [
                'level' => 2,
                'name' => 'Enthusiast',
                'points_required' => 200,
                'description' => 'You\'re becoming a regular! Continue participating to unlock more benefits.',
                'rewards' => json_encode([
                    'badge' => 'enthusiast_badge',
                    'features' => ['basic_access', 'profile_customization']
                ])
            ],
            [
                'level' => 3,
                'name' => 'Explorer',
                'points_required' => 500,
                'description' => 'You\'re exploring the platform and building connections.',
                'rewards' => json_encode([
                    'badge' => 'explorer_badge',
                    'features' => ['basic_access', 'profile_customization', 'extended_readlists']
                ])
            ],
            [
                'level' => 4,
                'name' => 'Scholar',
                'points_required' => 1000,
                'description' => 'Your dedication to learning is impressive!',
                'rewards' => json_encode([
                    'badge' => 'scholar_badge',
                    'features' => ['basic_access', 'profile_customization', 'extended_readlists', 'priority_support']
                ])
            ],
            [
                'level' => 5,
                'name' => 'Influencer',
                'points_required' => 2500,
                'description' => 'You\'re making a significant impact on the community.',
                'rewards' => json_encode([
                    'badge' => 'influencer_badge',
                    'features' => ['basic_access', 'profile_customization', 'extended_readlists', 'priority_support', 'beta_access']
                ])
            ],
            [
                'level' => 6,
                'name' => 'Expert',
                'points_required' => 5000,
                'description' => 'Your expertise and contributions are highly valued.',
                'rewards' => json_encode([
                    'badge' => 'expert_badge',
                    'features' => ['basic_access', 'profile_customization', 'extended_readlists', 'priority_support', 'beta_access', 'exclusive_content']
                ])
            ],
            [
                'level' => 7,
                'name' => 'Master',
                'points_required' => 10000,
                'description' => 'You\'ve mastered the platform and are a pillar of the community.',
                'rewards' => json_encode([
                    'badge' => 'master_badge',
                    'features' => ['basic_access', 'profile_customization', 'extended_readlists', 'priority_support', 'beta_access', 'exclusive_content', 'premium_perks']
                ])
            ],
            [
                'level' => 8,
                'name' => 'Virtuoso',
                'points_required' => 25000,
                'description' => 'Your exceptional contributions set you apart as a platform virtuoso.',
                'rewards' => json_encode([
                    'badge' => 'virtuoso_badge',
                    'features' => ['all_features', 'special_recognition']
                ])
            ],
        ];
        
        foreach ($levels as $level) {
            AlexPointsLevel::updateOrCreate(
                ['level' => $level['level']],
                $level
            );
            
            $this->line("  - Seeded level: {$level['name']} (Level {$level['level']})");
        }
    }
}