<?php
namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\PaymentLog;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaystackController extends Controller
{
    protected $paystackService;

    public function __construct(PaystackService $paystackService)
    {
        $this->paystackService = $paystackService;
    }

    /**
     * Create subscription plans or get existing ones
     */
    private function getOrCreatePlan($planType)
    {
        $plans = [
            'monthly' => [
                'name' => 'Monthly Subscription',
                'amount' => 5000, // 5,000 naira
                'interval' => 'monthly',
                'description' => 'Access to all features for one month'
            ],
            'yearly' => [
                'name' => 'Yearly Subscription',
                'amount' => 50000, // 50,000 naira
                'interval' => 'annually',
                'description' => 'Access to all features for one year at a discounted rate'
            ]
        ];

        if (!isset($plans[$planType])) {
            return null;
        }

        $planConfig = $plans[$planType];

        // Try to find existing plan with the same name
        $existingPlans = $this->paystackService->listPlans();
        
        if ($existingPlans['success']) {
            $data = $existingPlans['data']['data'];
            foreach ($data as $plan) {
                if ($plan['name'] === $planConfig['name']) {
                    return [
                        'success' => true,
                        'plan_code' => $plan['plan_code'],
                        'amount' => $plan['amount'] / 100, // Convert from kobo to naira
                    ];
                }
            }
        }

        // Create a new plan if not found
        $newPlan = $this->paystackService->createPlan(
            $planConfig['name'],
            $planConfig['amount'],
            $planConfig['interval'],
            $planConfig['description']
        );

        if (!$newPlan['success']) {
            return $newPlan;
        }

        return [
            'success' => true,
            'plan_code' => $newPlan['data']['data']['plan_code'],
            'amount' => $planConfig['amount'],
        ];
    }

    /**
     * Initiate a subscription
     */
    public function initiateSubscription(Request $request)
    {
        $validated = $request->validate([
            'plan_type' => 'required|string|in:monthly,yearly'
        ]);

        $user = auth()->user();
        
        try {
            DB::beginTransaction();
            
            // Get or create the plan
            $plan = $this->getOrCreatePlan($validated['plan_type']);
            
            if (!$plan || !$plan['success']) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Failed to setup subscription plan',
                    'errors' => $plan['message'] ?? 'Unknown error'
                ], 500);
            }
            
            // Generate a unique reference
            $reference = 'sub_' . uniqid() . '_' . time();
            
            // Initialize the transaction with plan
            $initResponse = $this->paystackService->initializeSubscription(
                $user->email,
                $plan['plan_code'],
                route('paystack.callback'),
                [
                    'plan_type' => $validated['plan_type'],
                    'user_id' => $user->id,
                    'is_recurring' => true
                ]
            );
            
            if (!$initResponse['success']) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Payment initialization failed',
                    'errors' => $initResponse['message']
                ], 500);
            }
            
            // Log the payment attempt
            PaymentLog::create([
                'user_id' => $user->id,
                'transaction_reference' => $initResponse['data']['data']['reference'],
                'payment_type' => 'subscription',
                'status' => 'pending',
                'amount' => $plan['amount'],
                'plan_type' => $validated['plan_type'],
                'response_data' => $initResponse['data'],
                'metadata' => json_encode([
                    'plan_code' => $plan['plan_code'],
                    'is_recurring' => true
                ])
            ]);
            
            DB::commit();
            
            // Return the authorization URL to redirect the user
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
            Log::error('Subscription initialization failed: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'An error occurred while initializing subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle callback after payment
     */
    public function handleCallback(Request $request)
    {
        $reference = $request->query('reference');
        
        if (!$reference) {
            return redirect()->route('subscription.failed')->with('error', 'No reference provided');
        }
        
        $verification = $this->paystackService->verifyTransaction($reference);
        
        if (!$verification['success']) {
            return redirect()->route('subscription.failed')->with('error', 'Payment verification failed');
        }
        
        if ($verification['data']['data']['status'] !== 'success') {
            return redirect()->route('subscription.failed')->with('error', 'Payment was not successful');
        }
        
        // Process the successful payment
        $this->processSuccessfulPayment($verification['data']['data']);
        
        return redirect()->route('subscription.success');
    }

    /**
     * Verify payment with reference
     */
    public function verifyPayment($reference)
    {
        $verification = $this->paystackService->verifyTransaction($reference);
        
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
        $subscription = $this->processSuccessfulPayment($data);
        
        return response()->json([
            'success' => true,
            'message' => 'Payment verification successful',
            'data' => [
                'subscription' => $subscription,
                'transaction' => $data
            ]
        ]);
    }

    /**
     * Process successful payment
     */
    private function processSuccessfulPayment($paymentData)
    {
        $reference = $paymentData['reference'];
        $metadata = $paymentData['metadata'] ?? null;
        
        // Find the payment log
        $paymentLog = PaymentLog::where('transaction_reference', $reference)->first();
        
        if (!$paymentLog) {
            Log::error('Payment log not found for reference: ' . $reference);
            return null;
        }
        
        try {
            DB::beginTransaction();
            
            // Update the payment log
            $paymentLog->status = 'success';
            $paymentLog->response_data = array_merge($paymentLog->response_data ?? [], ['verification' => $paymentData]);
            $paymentLog->save();
            
            if ($paymentLog->payment_type === 'subscription') {
                // Get plan type from metadata
                $planType = $metadata['plan_type'] ?? $paymentLog->plan_type;
                $isRecurring = $metadata['is_recurring'] ?? true;
                
                // Get subscription duration
                $duration = $planType === 'monthly' ? 30 : 365;
                
                // Create or update subscription
                $subscription = Subscription::updateOrCreate(
                    [
                        'user_id' => $paymentLog->user_id,
                        'paystack_reference' => $reference
                    ],
                    [
                        'paystack_subscription_code' => $paymentData['subscription_code'] ?? null,
                        'paystack_email_token' => $paymentData['email_token'] ?? null,
                        'plan_type' => $planType,
                        'plan_code' => $paymentData['plan'] ?? null,
                        'amount' => $paymentLog->amount,
                        'status' => 'active',
                        'starts_at' => now(),
                        'expires_at' => now()->addDays($duration),
                        'is_active' => true,
                        'is_recurring' => $isRecurring
                    ]
                );
                
                DB::commit();
                return $subscription;
            }
            
            DB::commit();
            return null;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing successful payment: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Handle Paystack webhook
     */
    public function handleWebhook(Request $request)
    {
        // Verify webhook signature
        if (!$this->paystackService->verifyWebhookSignature(
            $request->getContent(),
            $request->header('x-paystack-signature')
        )) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }
        
        $event = $request->input('event');
        $data = $request->input('data');
        
        // Log the webhook event
        Log::info('Paystack webhook received: ' . $event);
        
        try {
            switch ($event) {
                case 'charge.success':
                    $this->handleChargeSuccess($data);
                    break;
                    
                case 'subscription.create':
                    $this->handleSubscriptionCreate($data);
                    break;
                    
                case 'subscription.disable':
                    $this->handleSubscriptionDisable($data);
                    break;
                    
                case 'invoice.update':
                    $this->handleInvoiceUpdate($data);
                    break;
                    
                case 'invoice.payment_failed':
                    $this->handlePaymentFailure($data);
                    break;
            }
            
            return response()->json(['message' => 'Webhook processed successfully']);
            
        } catch (\Exception $e) {
            Log::error('Error processing webhook: ' . $e->getMessage());
            return response()->json(['message' => 'Error processing webhook'], 500);
        }
    }

    /**
     * Handle charge.success webhook event
     */
    private function handleChargeSuccess($data)
    {
        $metadata = $data['metadata'] ?? null;
        
        // If this is a subscription payment, process it
        if (isset($data['subscription_code'])) {
            // Find subscription by code
            $subscription = Subscription::where('paystack_subscription_code', $data['subscription_code'])->first();
            
            if ($subscription) {
                // Update subscription
                $duration = $subscription->plan_type === 'monthly' ? 30 : 365;
                $subscription->expires_at = now()->addDays($duration);
                $subscription->status = 'active';
                $subscription->is_active = true;
                $subscription->save();
                
                // Create payment log
                PaymentLog::create([
                    'user_id' => $subscription->user_id,
                    'transaction_reference' => $data['reference'],
                    'payment_type' => 'subscription_renewal',
                    'status' => 'success',
                    'amount' => $data['amount'] / 100,
                    'plan_type' => $subscription->plan_type,
                    'paystack_code' => $data['subscription_code'],
                    'response_data' => $data
                ]);
            }
        }
        // Otherwise, try to process as a standard payment
        else if ($metadata && isset($metadata['user_id'])) {
            $this->processSuccessfulPayment($data);
        }
    }

    /**
     * Handle subscription.create webhook event
     */
    private function handleSubscriptionCreate($data)
    {
        // Find the subscription by customer code and subscription code
        $subscription = Subscription::where('paystack_subscription_code', $data['subscription_code'])->first();
        
        if (!$subscription) {
            // Try to find by customer code
            $user = User::where('email', $data['customer']['email'])->first();
            
            if (!$user) {
                return;
            }
            
            // Find latest payment log for this user
            $paymentLog = PaymentLog::where('user_id', $user->id)
                ->where('payment_type', 'subscription')
                ->orderBy('created_at', 'desc')
                ->first();
            
            if (!$paymentLog) {
                return;
            }
            
            // Create subscription
            $planType = $paymentLog->plan_type ?? 'monthly';
            $duration = $planType === 'monthly' ? 30 : 365;
            
            Subscription::create([
                'user_id' => $user->id,
                'paystack_reference' => $paymentLog->transaction_reference,
                'paystack_subscription_code' => $data['subscription_code'],
                'paystack_email_token' => $data['email_token'],
                'plan_type' => $planType,
                'plan_code' => $data['plan']['plan_code'],
                'amount' => $data['plan']['amount'] / 100,
                'status' => 'active',
                'starts_at' => now(),
                'expires_at' => now()->addDays($duration),
                'is_active' => true,
                'is_recurring' => true
            ]);
        } else {
            // Update existing subscription
            $subscription->paystack_subscription_code = $data['subscription_code'];
            $subscription->paystack_email_token = $data['email_token'];
            $subscription->plan_code = $data['plan']['plan_code'];
            $subscription->amount = $data['plan']['amount'] / 100;
            $subscription->status = 'active';
            $subscription->is_active = true;
            $subscription->is_recurring = true;
            $subscription->save();
        }
    }

    /**
     * Handle subscription.disable webhook event
     */
    private function handleSubscriptionDisable($data)
    {
        $subscription = Subscription::where('paystack_subscription_code', $data['subscription_code'])->first();
        
        if ($subscription) {
            $subscription->status = 'cancelled';
            $subscription->is_recurring = false;
            $subscription->save();
        }
    }

    /**
     * Handle invoice.update webhook event
     */
    private function handleInvoiceUpdate($data)
    {
        // Only process paid invoices
        if ($data['paid']) {
            $subscription = Subscription::where('paystack_subscription_code', $data['subscription']['subscription_code'])->first();
            
            if ($subscription) {
                // Extend subscription
                $duration = $subscription->plan_type === 'monthly' ? 30 : 365;
                $subscription->expires_at = now()->addDays($duration);
                $subscription->status = 'active';
                $subscription->is_active = true;
                $subscription->save();
                
                // Create payment log
                PaymentLog::create([
                    'user_id' => $subscription->user_id,
                    'transaction_reference' => $data['transaction']['reference'],
                    'payment_type' => 'subscription_renewal',
                    'status' => 'success',
                    'amount' => $data['amount'] / 100,
                    'plan_type' => $subscription->plan_type,
                    'paystack_code' => $data['subscription']['subscription_code'],
                    'response_data' => $data
                ]);
            }
        }
    }

    /**
     * Handle invoice.payment_failed webhook event
     */
    private function handlePaymentFailure($data)
    {
        $subscription = Subscription::where('paystack_subscription_code', $data['subscription']['subscription_code'])->first();
        
        if ($subscription) {
            // Log the failure
            PaymentLog::create([
                'user_id' => $subscription->user_id,
                'transaction_reference' => $data['transaction']['reference'] ?? ('failed_' . uniqid()),
                'payment_type' => 'subscription_renewal_failed',
                'status' => 'failed',
                'amount' => $data['amount'] / 100,
                'plan_type' => $subscription->plan_type,
                'paystack_code' => $data['subscription']['subscription_code'],
                'response_data' => $data
            ]);
            
            // Note: We don't disable the subscription here - Paystack will try again
            // and will send a subscription.disable event if it gives up
        }
    }

    /**
     * Cancel a subscription
     */
    public function cancelSubscription(Request $request)
    {
        $user = auth()->user();
        
        $subscription = Subscription::where('user_id', $user->id)
            ->where('is_active', true)
            ->where('is_recurring', true)
            ->first();
        
        if (!$subscription) {
            return response()->json([
                'message' => 'No active recurring subscription found'
            ], 404);
        }
        
        if (!$subscription->paystack_subscription_code || !$subscription->paystack_email_token) {
            // Simple non-recurring subscription, just mark as cancelled
            $subscription->cancel();
            
            return response()->json([
                'message' => 'Subscription cancelled successfully'
            ]);
        }
        
        // Try to cancel the subscription at Paystack
        $result = $this->paystackService->disableSubscription(
            $subscription->paystack_subscription_code,
            $subscription->paystack_email_token
        );
        
        if (!$result['success']) {
            // Still mark it as cancelled locally even if Paystack fails
            $subscription->cancel();
            
            return response()->json([
                'message' => 'Subscription cancelled locally but failed to notify payment provider',
                'errors' => $result['message']
            ], 207); // 207 Multi-Status
        }
        
        // Update subscription status
        $subscription->cancel();
        
        return response()->json([
            'message' => 'Subscription cancelled successfully'
        ]);
    }

    /**
     * Create a free subscription for testing
     */
    public function createFreeSubscription(Request $request)
    {
        $request->validate([
            'plan_type' => 'required|string|in:monthly,yearly'
        ]);
        
        $user = auth()->user();
        $duration = $request->plan_type === 'monthly' ? 30 : 365;
        
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'paystack_reference' => 'free_' . uniqid(),
            'plan_type' => $request->plan_type,
            'amount' => 0,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addDays($duration),
            'is_active' => true,
            'is_recurring' => false
        ]);
        
        return response()->json([
            'message' => 'Free subscription created successfully',
            'subscription' => $subscription
        ]);
    }

    /**
     * Retry a failed payment
     */
    public function retryPayment(Request $request)
    {
        $request->validate([
            'payment_log_id' => 'required|exists:payment_logs,id'
        ]);
        
        $paymentLog = PaymentLog::findOrFail($request->payment_log_id);
        
        // Check if this payment log belongs to the authenticated user
        if ($paymentLog->user_id !== auth()->id()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        
        // Only allow retrying failed payments
        if ($paymentLog->status !== 'failed') {
            return response()->json([
                'message' => 'Only failed payments can be retried'
            ], 400);
        }
        
        try {
            DB::beginTransaction();
            
            // Generate a new reference
            $reference = $paymentLog->payment_type . '_retry_' . uniqid() . '_' . time();
            
            $callbackUrl = $paymentLog->payment_type === 'subscription' 
                ? route('paystack.callback')
                : route('enrollment.verify');
                
            // Initialize the transaction
            if ($paymentLog->payment_type === 'subscription') {
                $metadata = json_decode($paymentLog->metadata, true) ?? [];
                $planCode = $metadata['plan_code'] ?? null;
                
                if (!$planCode) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Cannot retry subscription without plan code'
                    ], 400);
                }
                
                $initResponse = $this->paystackService->initializeSubscription(
                    auth()->user()->email,
                    $planCode,
                    $callbackUrl,
                    [
                        'plan_type' => $paymentLog->plan_type,
                        'user_id' => auth()->id(),
                        'is_recurring' => true,
                        'retry_for' => $paymentLog->id
                    ]
                );
            } else {
                $initResponse = $this->paystackService->initializeTransaction(
                    auth()->user()->email,
                    $paymentLog->amount,
                    $callbackUrl,
                    [
                        'payment_type' => $paymentLog->payment_type,
                        'user_id' => auth()->id(),
                        'course_id' => $paymentLog->course_id,
                        'retry_for' => $paymentLog->id
                    ]
                );
            }
            
            if (!$initResponse['success']) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Payment initialization failed',
                    'errors' => $initResponse['message']
                ], 500);
            }
            
            // Create a new payment log for this retry
            $newLog = PaymentLog::create([
                'user_id' => auth()->id(),
                'transaction_reference' => $initResponse['data']['data']['reference'],
                'payment_type' => $paymentLog->payment_type,
                'status' => 'pending',
                'amount' => $paymentLog->amount,
                'course_id' => $paymentLog->course_id,
                'plan_type' => $paymentLog->plan_type,
                'metadata' => $paymentLog->metadata,
                'response_data' => $initResponse['data']
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Payment retry initialized',
                'data' => [
                    'authorization_url' => $initResponse['data']['data']['authorization_url'],
                    'reference' => $initResponse['data']['data']['reference'],
                    'payment_log_id' => $newLog->id
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment retry failed: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'An error occurred while retrying payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}