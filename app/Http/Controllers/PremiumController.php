<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PremiumController extends Controller
{
    protected $paystackService;

    public function __construct(PaystackService $paystackService)
    {
        $this->paystackService = $paystackService;
    }

    /**
     * Get premium features and subscription status for the current user
     */
    public function getPremiumStatus()
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Get active subscription if any
        $subscription = $user->activeSubscription;
        $hasPremium = $subscription !== null;
        
        // Get all available plans
        $plans = SubscriptionPlan::where('is_active', true)->get();
        
        // Get current limits
        $mediaLimits = [
            'max_video_size_mb' => round($user->getMaxVideoSizeKb() / 1024, 1),
            'max_image_size_mb' => round($user->getMaxImageSizeKb() / 1024, 1)
        ];
        
        // Get premium features
        $premiumFeatures = [
            'can_analyze_posts' => $user->canAnalyzePosts(),
            'media_limits' => $mediaLimits,
            'is_premium' => $hasPremium,
            'can_create_channels' => $user->canCreateChannels(),
            'can_join_live_classes' => $hasPremium
        ];
        
        // Format subscription details if present
        $subscriptionDetails = null;
        if ($subscription) {
            $subscriptionDetails = [
                'id' => $subscription->id,
                'plan_type' => $subscription->plan_type,
                'status' => $subscription->status,
                'expires_at' => $subscription->expires_at,
                'days_remaining' => $subscription->daysRemaining(),
                'is_recurring' => $subscription->is_recurring,
                'available_credits' => $subscription->available_credits
            ];
        }
        
        return response()->json([
            'has_premium' => $hasPremium,
            'premium_features' => $premiumFeatures,
            'subscription' => $subscriptionDetails,
            'available_plans' => $plans
        ]);
    }
    
    /**
     * Initialize a premium subscription purchase
     */
    public function initiatePremiumPurchase(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Validate request
        $validated = $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id'
        ]);
        
        // Check if user already has an active subscription
        if ($user->hasActiveSubscription()) {
            return response()->json([
                'message' => 'You already have an active premium subscription',
                'subscription' => $user->activeSubscription
            ], 400);
        }
        
        // Get the selected plan
        $plan = SubscriptionPlan::findOrFail($validated['plan_id']);
        
        // Initialize payment with Paystack
        try {
            // Check if this plan has a Paystack plan code
            if (empty($plan->paystack_plan_code)) {
                // Create the plan in Paystack
                $paystackPlan = $this->paystackService->createPlan(
                    $plan->name,
                    $plan->price * 100, // Convert to kobo
                    $plan->type === 'monthly' ? 'monthly' : 'annually',
                    $plan->description
                );
                
                if (!$paystackPlan['success']) {
                    return response()->json([
                        'message' => 'Failed to create subscription plan',
                        'error' => $paystackPlan['message']
                    ], 500);
                }
                
                // Update the plan with the Paystack plan code
                $plan->paystack_plan_code = $paystackPlan['data']['data']['plan_code'];
                $plan->save();
            }
            
            // Initialize the transaction with Paystack
            $initResponse = $this->paystackService->initializeSubscription(
                $user->email,
                $plan->paystack_plan_code,
                route('subscriptions.verify'),
                [
                    'plan_id' => $plan->id,
                    'user_id' => $user->id,
                    'is_recurring' => true
                ]
            );
            
            if (!$initResponse['success']) {
                return response()->json([
                    'message' => 'Payment initialization failed',
                    'error' => $initResponse['message']
                ], 500);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Premium subscription initialized',
                'payment_data' => [
                    'authorization_url' => $initResponse['data']['data']['authorization_url'],
                    'reference' => $initResponse['data']['data']['reference'],
                    'access_code' => $initResponse['data']['data']['access_code']
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Failed to initialize premium purchase: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'An error occurred while initializing premium purchase',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get premium features comparison
     */
    public function getPremiumFeatures()
    {
        $features = [
            'free' => [
                'name' => 'Free',
                'price' => 'Free',
                'features' => [
                    'Upload videos up to 50MB',
                    'Upload images up to 5MB',
                    'Create and join channels',
                    'Follow other users',
                    'Like and comment on posts',
                    'Basic content creation'
                ]
            ],
            'premium' => [
                'name' => 'Premium',
                'price' => 'From $8/month',
                'features' => [
                    'Upload videos up to 500MB',
                    'Upload images up to 50MB',
                    'AI-powered post analysis',
                    'Ad-free experience',
                    'Join live classes',
                    'Redeem points for real use cases',
                    'Access to all courses/contents',
                    'Access to all Library contents',
                    'Create Collaboration Channels',
                    'Enhanced customer support'
                ]
            ]
        ];
        
        return response()->json([
            'premium_features_comparison' => $features
        ]);
    }
}