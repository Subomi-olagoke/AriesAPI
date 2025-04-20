<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    protected $paystackService;

    public function __construct(PaystackService $paystackService)
    {
        $this->paystackService = $paystackService;
    }

    /**
     * Get all subscription plans
     */
    public function getPlans()
    {
        $plans = SubscriptionPlan::where('is_active', true)->get();
        
        return response()->json([
            'plans' => $plans
        ]);
    }

    /**
     * Get all subscriptions for the authenticated user
     */
    public function index()
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        $subscriptions = $user->subscriptions()
            ->with('plan')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'subscriptions' => $subscriptions,
            'has_active_subscription' => $user->hasActiveSubscription()
        ]);
    }

    /**
     * Get the current active subscription for the authenticated user
     */
    public function current()
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        $subscription = $user->activeSubscription;

        if (!$subscription) {
            $plans = SubscriptionPlan::where('is_active', true)->get();
            
            return response()->json([
                'has_subscription' => false,
                'message' => 'No active subscription found',
                'plans' => $plans
            ]);
        }

        // Load the plan relationship
        $subscription->load('plan');
        
        return response()->json([
            'has_subscription' => true,
            'subscription' => [
                'id' => $subscription->id,
                'plan' => $subscription->plan,
                'plan_type' => $subscription->plan_type,
                'started_at' => $subscription->starts_at,
                'expires_at' => $subscription->expires_at,
                'days_remaining' => $subscription->daysRemaining(),
                'is_active' => $subscription->is_active,
                'is_recurring' => $subscription->is_recurring,
                'can_create_channels' => $subscription->can_create_channels,
                'available_credits' => $subscription->available_credits
            ]
        ]);
    }

    /**
     * Get upcoming subscription if any
     */
    public function upcoming()
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        $subscription = $user->subscriptions()
            ->with('plan')
            ->where('is_active', true)
            ->where('starts_at', '>', now())
            ->first();

        return response()->json([
            'has_upcoming_subscription' => !!$subscription,
            'subscription' => $subscription
        ]);
    }

    /**
     * Get subscription history
     */
    public function history()
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        $subscriptions = $user->subscriptions()
            ->with('plan')
            ->where('starts_at', '<', now())
            ->orderBy('starts_at', 'desc')
            ->get()
            ->map(function ($subscription) {
                return [
                    'id' => $subscription->id,
                    'plan' => $subscription->plan,
                    'plan_type' => $subscription->plan_type,
                    'started_at' => $subscription->starts_at,
                    'expired_at' => $subscription->expires_at,
                    'amount_paid' => $subscription->amount,
                    'reference' => $subscription->paystack_reference
                ];
            });

        return response()->json([
            'subscription_history' => $subscriptions
        ]);
    }
    
    /**
     * Subscribe to a plan
     */
    public function subscribe(Request $request)
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
                'message' => 'You already have an active subscription'
            ], 400);
        }
        
        // Get the plan
        $plan = SubscriptionPlan::findOrFail($validated['plan_id']);
        
        try {
            DB::beginTransaction();
            
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
                    DB::rollBack();
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
                DB::rollBack();
                return response()->json([
                    'message' => 'Payment initialization failed',
                    'error' => $initResponse['message']
                ], 500);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Subscription initialized',
                'data' => [
                    'authorization_url' => $initResponse['data']['data']['authorization_url'],
                    'reference' => $initResponse['data']['data']['reference'],
                    'access_code' => $initResponse['data']['data']['access_code']
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create subscription: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'An error occurred while creating subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Verify subscription payment
     */
    public function verify(Request $request)
    {
        // Validate request
        $validated = $request->validate([
            'reference' => 'required|string'
        ]);
        
        $verification = $this->paystackService->verifyTransaction($validated['reference']);
        
        if (!$verification['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed'
            ], 400);
        }
        
        $data = $verification['data']['data'];
        
        if ($data['status'] !== 'success') {
            return response()->json([
                'success' => false,
                'message' => 'Payment was not successful',
                'status' => $data['status']
            ], 400);
        }
        
        // Process the successful payment
        try {
            DB::beginTransaction();
            
            $metadata = $data['metadata'] ?? [];
            
            // Get the plan
            $plan = SubscriptionPlan::findOrFail($metadata['plan_id'] ?? 0);
            
            // Get the user
            $user = User::findOrFail($metadata['user_id'] ?? auth()->id());
            
            // Create subscription
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'paystack_reference' => $data['reference'],
                'paystack_subscription_code' => $data['subscription_code'] ?? null,
                'paystack_email_token' => $data['email_token'] ?? null,
                'plan_type' => $plan->type,
                'plan_code' => $plan->paystack_plan_code,
                'amount' => $plan->price,
                'status' => 'active',
                'starts_at' => now(),
                'expires_at' => now()->addDays($plan->getDurationInDays()),
                'is_active' => true,
                'is_recurring' => true,
                'can_create_channels' => true,
                'available_credits' => 100 // Start with 100 credits
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Subscription created successfully',
                'subscription' => $subscription->load('plan')
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process subscription: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'An error occurred while processing subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Cancel a subscription
     */
    public function cancel()
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        $subscription = $user->activeSubscription;
        
        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription found'
            ], 404);
        }
        
        try {
            // If subscription is recurring, cancel it with Paystack
            if ($subscription->isRecurring() && $subscription->paystack_subscription_code) {
                $result = $this->paystackService->disableSubscription(
                    $subscription->paystack_subscription_code,
                    $subscription->paystack_email_token
                );
                
                if (!$result['success']) {
                    // Still cancel locally even if Paystack fails
                    $subscription->cancel();
                    
                    return response()->json([
                        'message' => 'Subscription cancelled locally but failed to notify payment provider',
                        'errors' => $result['message']
                    ], 207); // 207 Multi-Status
                }
            }
            
            // Cancel the subscription
            $subscription->cancel();
            
            return response()->json([
                'message' => 'Subscription cancelled successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to cancel subscription: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'An error occurred while cancelling subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Use credits for a feature
     */
    public function useCredits(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Validate request
        $validated = $request->validate([
            'credits' => 'required|integer|min:1',
            'feature' => 'required|string'
        ]);
        
        $subscription = $user->activeSubscription;
        
        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription found'
            ], 404);
        }
        
        if ($subscription->available_credits < $validated['credits']) {
            return response()->json([
                'message' => 'Not enough credits available',
                'available_credits' => $subscription->available_credits,
                'required_credits' => $validated['credits']
            ], 400);
        }
        
        try {
            // Use the credits
            $subscription->useCredits($validated['credits']);
            
            return response()->json([
                'message' => 'Credits used successfully',
                'feature' => $validated['feature'],
                'credits_used' => $validated['credits'],
                'remaining_credits' => $subscription->available_credits
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to use credits: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'An error occurred while using credits',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
