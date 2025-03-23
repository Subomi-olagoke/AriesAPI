<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PaymentMethodController extends Controller
{
    protected $paystackService;
    
    public function __construct(PaystackService $paystackService)
    {
        $this->paystackService = $paystackService;
        $this->middleware('auth:sanctum');
    }
    
    /**
     * List the user's saved payment methods
     */
    public function index()
    {
        $user = auth()->user();
        $paymentMethods = $user->paymentMethods()->get();
        
        return response()->json([
            'payment_methods' => $paymentMethods
        ]);
    }
    
    /**
     * Initiate adding a new payment method
     */
    public function initiate(Request $request)
    {
        $user = auth()->user();
        
        // Generate a small test charge amount (will be refunded)
        $testAmount = 50; // 50 naira 
        
        try {
            // Initialize a transaction with card authorization
            $result = $this->paystackService->initializeTransactionWithAuthorization(
                $user->email,
                $testAmount,
                route('payment-methods.verify'),
                [
                    'user_id' => $user->id,
                    'process_type' => 'add_payment_method'
                ]
            );
            
            if (!$result['success']) {
                return response()->json([
                    'message' => 'Failed to initialize payment method addition',
                    'error' => $result['message']
                ], 500);
            }
            
            return response()->json([
                'message' => 'Please complete card authorization',
                'authorization_url' => $result['data']['data']['authorization_url'],
                'reference' => $result['data']['data']['reference']
            ]);
        } catch (\Exception $e) {
            Log::error('Payment method initialization failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to initiate card authorization',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Verify and save a payment method after authorization
     */
    public function verify(Request $request)
    {
        $reference = $request->query('reference');
        
        if (!$reference) {
            return redirect()->route('payment-methods.failed')->with('error', 'No reference provided');
        }
        
        try {
            DB::beginTransaction();
            
            // Verify the transaction
            $verification = $this->paystackService->verifyTransaction($reference);
            
            if (!$verification['success']) {
                DB::rollBack();
                return redirect()->route('payment-methods.failed')->with('error', 'Payment verification failed');
            }
            
            $data = $verification['data']['data'];
            
            if ($data['status'] !== 'success') {
                DB::rollBack();
                return redirect()->route('payment-methods.failed')->with('error', 'Card authorization failed');
            }
            
            // Extract user ID from metadata
            $userId = $data['metadata']['user_id'] ?? null;
            
            if (!$userId) {
                DB::rollBack();
                return redirect()->route('payment-methods.failed')->with('error', 'User identification failed');
            }
            
            $user = \App\Models\User::find($userId);
            
            if (!$user) {
                DB::rollBack();
                return redirect()->route('payment-methods.failed')->with('error', 'User not found');
            }
            
            $authorization = $data['authorization'];
            
            // Check if this card is already saved
            $existingCard = PaymentMethod::where('user_id', $user->id)
                ->where('last_four', $authorization['last4'])
                ->where('card_type', $authorization['card_type'])
                ->where('expiry_month', $authorization['exp_month'])
                ->where('expiry_year', $authorization['exp_year'])
                ->first();
                
            if ($existingCard) {
                // Update the existing card with new authorization code
                $existingCard->update([
                    'paystack_authorization_code' => $authorization['authorization_code'],
                    'is_active' => true
                ]);
                
                $paymentMethod = $existingCard;
            } else {
                // Make this the default card if it's the first one
                $isDefault = $user->paymentMethods()->count() === 0;
                
                // Create a new payment method
                $paymentMethod = PaymentMethod::create([
                    'user_id' => $user->id,
                    'paystack_authorization_code' => $authorization['authorization_code'],
                    'paystack_customer_code' => $data['customer']['customer_code'] ?? null,
                    'card_type' => $authorization['card_type'],
                    'last_four' => $authorization['last4'],
                    'expiry_month' => $authorization['exp_month'],
                    'expiry_year' => $authorization['exp_year'],
                    'bank_name' => $authorization['bank'],
                    'is_default' => $isDefault,
                    'is_active' => true
                ]);
            }
            
            // Initiate refund for the test amount
            $this->paystackService->refundTransaction($reference, 'Test authorization for card storage');
            
            DB::commit();
            
            return redirect()->route('payment-methods.success')->with('message', 'Payment method added successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment method verification failed: ' . $e->getMessage());
            return redirect()->route('payment-methods.failed')->with('error', 'Failed to verify payment method: ' . $e->getMessage());
        }
    }
    
    /**
     * Make a payment method the default
     */
    public function setDefault($id)
    {
        $user = auth()->user();
        
        try {
            DB::beginTransaction();
            
            // First, set all user's payment methods to non-default
            PaymentMethod::where('user_id', $user->id)
                ->update(['is_default' => false]);
                
            // Then, set the selected one as default
            $paymentMethod = PaymentMethod::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();
                
            $paymentMethod->update(['is_default' => true]);
            
            DB::commit();
            
            return response()->json([
                'message' => 'Default payment method updated',
                'payment_method' => $paymentMethod
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to set default payment method: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update default payment method',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Remove a payment method
     */
    public function destroy($id)
    {
        $user = auth()->user();
        
        try {
            $paymentMethod = PaymentMethod::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();
                
            $wasDefault = $paymentMethod->is_default;
            
            // Delete the payment method
            $paymentMethod->delete();
            
            // If this was the default, set another one as default
            if ($wasDefault) {
                $newDefault = PaymentMethod::where('user_id', $user->id)
                    ->first();
                    
                if ($newDefault) {
                    $newDefault->update(['is_default' => true]);
                }
            }
            
            return response()->json([
                'message' => 'Payment method removed successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to remove payment method: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to remove payment method',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}