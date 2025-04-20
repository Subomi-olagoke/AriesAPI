<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Default subscription plans
        $plans = [
            [
                'name' => 'Monthly Premium',
                'code' => 'monthly_premium',
                'type' => 'monthly',
                'price' => 8.00,
                'description' => 'Monthly subscription with all premium features',
                'features' => json_encode([
                    'No ads',
                    'Join live classes',
                    'Redeem points for real use cases',
                    'Access to all courses/contents',
                    'Access to all Library contents',
                    'Can create Collaboration Channels'
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Yearly Premium',
                'code' => 'yearly_premium',
                'type' => 'yearly',
                'price' => 80.00, // 10 months, 2 months free
                'description' => 'Yearly subscription with all premium features at a discounted rate',
                'features' => json_encode([
                    'No ads',
                    'Join live classes',
                    'Redeem points for real use cases',
                    'Access to all courses/contents',
                    'Access to all Library contents',
                    'Can create Collaboration Channels',
                    '2 months free compared to monthly plan'
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];
        
        DB::table('subscription_plans')->insert($plans);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('subscription_plans')->whereIn('code', ['monthly_premium', 'yearly_premium'])->delete();
    }
};
