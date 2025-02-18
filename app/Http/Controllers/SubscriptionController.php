<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index()
    {
        $subscriptions = Subscription::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'subscriptions' => $subscriptions,
            'has_active_subscription' => $subscriptions->contains(function ($subscription) {
                return $subscription->is_active && $subscription->expires_at->isFuture();
            })
        ]);
    }

    public function current()
    {
        $subscription = Subscription::where('user_id', auth()->id())
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();

        if (!$subscription) {
            return response()->json([
                'has_subscription' => false,
                'message' => 'No active subscription found',
                'plans' => [
                    [
                        'type' => 'monthly',
                        'price' => 5000,
                        'features' => [
                            'Access to all live classes',
                            'Chat during classes',
                            'Screen sharing',
                            'HD video quality'
                        ]
                    ],
                    [
                        'type' => 'yearly',
                        'price' => 50000,
                        'features' => [
                            'All monthly features',
                            '2 months free',
                            'Priority support',
                            'Recording access'
                        ]
                    ]
                ]
            ]);
        }

        return response()->json([
            'has_subscription' => true,
            'subscription' => [
                'id' => $subscription->id,
                'plan_type' => $subscription->plan_type,
                'started_at' => $subscription->starts_at,
                'expires_at' => $subscription->expires_at,
                'days_remaining' => now()->diffInDays($subscription->expires_at),
                'is_active' => $subscription->is_active,
                'features' => $this->getPlanFeatures($subscription->plan_type)
            ]
        ]);
    }

    private function getPlanFeatures($planType)
    {
        $features = [
            'monthly' => [
                'Access to all live classes',
                'Chat during classes',
                'Screen sharing',
                'HD video quality'
            ],
            'yearly' => [
                'Access to all live classes',
                'Chat during classes',
                'Screen sharing',
                'HD video quality',
                'Priority support',
                'Recording access'
            ]
        ];

        return $features[$planType] ?? [];
    }

    public function upcoming()
    {
        $subscription = Subscription::where('user_id', auth()->id())
            ->where('is_active', true)
            ->where('starts_at', '>', now())
            ->first();

        return response()->json([
            'has_upcoming_subscription' => !!$subscription,
            'subscription' => $subscription
        ]);
    }

    public function history()
    {
        $subscriptions = Subscription::where('user_id', auth()->id())
            ->where('starts_at', '<', now())
            ->orderBy('starts_at', 'desc')
            ->get()
            ->map(function ($subscription) {
                return [
                    'id' => $subscription->id,
                    'plan_type' => $subscription->plan_type,
                    'started_at' => $subscription->starts_at,
                    'expired_at' => $subscription->expires_at,
                    'amount_paid' => $subscription->plan_type === 'monthly' ? 5000 : 50000,
                    'reference' => $subscription->paystack_reference
                ];
            });

        return response()->json([
            'subscription_history' => $subscriptions
        ]);
    }
}
