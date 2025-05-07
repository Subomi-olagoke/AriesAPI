<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PremiumStorefrontController extends Controller
{
    /**
     * Display the premium subscription storefront
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Get all available subscription plans
        $plans = SubscriptionPlan::where('is_active', true)->get();
        
        // Check if user is authenticated
        $user = Auth::user();
        $hasActivePlan = false;
        $currentPlan = null;
        
        if ($user) {
            $hasActivePlan = $user->hasActiveSubscription();
            if ($hasActivePlan) {
                $currentPlan = $user->activeSubscription->plan;
            }
        }
        
        return view('premium.storefront', [
            'plans' => $plans,
            'hasActivePlan' => $hasActivePlan,
            'currentPlan' => $currentPlan
        ]);
    }
    
    /**
     * Process subscription request from the storefront
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function subscribe(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return redirect()->route('login')->with('message', 'Please log in to subscribe to Premium');
        }
        
        // Validate the plan parameter
        $validated = $request->validate([
            'plan' => 'required|in:monthly,yearly'
        ]);
        
        // Get the plan ID
        $planType = $validated['plan'];
        $plan = SubscriptionPlan::where('type', $planType)->first();
        
        if (!$plan) {
            return back()->with('error', 'Invalid subscription plan selected');
        }
        
        // If user already has an active subscription
        if ($user->hasActiveSubscription()) {
            return back()->with('error', 'You already have an active subscription');
        }
        
        // Initialize a new premium controller and redirect to API
        $premiumController = new PremiumController(app(\App\Services\PaystackService::class));
        
        try {
            $request = new Request(['plan_id' => $plan->id]);
            $response = $premiumController->initiatePremiumPurchase($request);
            
            $data = $response->getData();
            
            if ($data->success) {
                // Redirect to Paystack authorization URL
                return redirect($data->payment_data->authorization_url);
            } else {
                return back()->with('error', $data->message ?? 'Failed to initialize payment');
            }
        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle success callback from payment gateway
     */
    public function success()
    {
        return view('premium.success');
    }
    
    /**
     * Handle failure callback from payment gateway
     */
    public function failed()
    {
        return view('premium.failed');
    }
}