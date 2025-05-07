<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->bigInteger('max_video_size_kb')->default(50000)->after('available_credits'); // 50MB default
            $table->bigInteger('max_image_size_kb')->default(5000)->after('max_video_size_kb'); // 5MB default
            $table->boolean('can_analyze_posts')->default(false)->after('max_image_size_kb');
        });
        
        // Update the features in the existing plans to include the new premium features
        DB::table('subscription_plans')
            ->where('code', 'monthly_premium')
            ->orWhere('code', 'yearly_premium')
            ->update([
                'features' => json_encode([
                    'No ads',
                    'Join live classes',
                    'Redeem points for real use cases',
                    'Access to all courses/contents',
                    'Access to all Library contents',
                    'Can create Collaboration Channels',
                    'Upload larger videos (up to 500MB)',
                    'Upload larger images (up to 50MB)',
                    'Analyze posts with AI'
                ])
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['max_video_size_kb', 'max_image_size_kb', 'can_analyze_posts']);
        });
    }
};