<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\HireRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\PaystackService;
use App\Notifications\HireRequestNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HireRequestController extends Controller
{
    protected $paystackService;

    public function __construct(PaystackService $paystackService)
    {
        $this->paystackService = $paystackService;
    }

    /**
     * Initiate a payment for hiring an educator
     */
    public function initiatePayment(Request $request)
    {
        $validated = $request->validate([
            'educator_username' => 'required|string|exists:users,username',
            'topic' => 'required|string|max:255',
            'message' => 'nullable|string',
            'duration' => 'required|string',
        ]);

        $user = auth()->user();
        $educator = User::where('username', $validated['educator_username'])
            ->where('role', User::ROLE_EDUCATOR)
            ->firstOrFail();

        // Get the educator's hire rate
        $profile = $educator->profile;
        if (!$profile || !$profile->hire_rate) {
            return response()->json([
                'message' => 'This educator has not set up their hiring rate yet'
            ], 400);
        }

        $amount = $profile->hire_rate;
        $currency = $profile->hire_currency ?? 'USD';

        try {
            // Generate payment reference
            $reference = 'hire_' . uniqid() . '_' . time();

            // Initialize payment with Paystack
            $initResponse = $this->paystackService->initializeTransaction(
                $user->email,
                $amount,
                route('hire.payment.verify'),
                [
                    'educator_id' => $educator->id,
                    'user_id' => $user->id,
                    'topic' => $validated['topic'],
                    'duration' => $validated['duration'],
                    'message' => $validated['message'] ?? '',
                    'payment_type' => 'educator_hire'
                ]
            );

            if (!$initResponse['success']) {
                return response()->json([
                    'message' => 'Payment initialization failed',
                    'error' => $initResponse['message']
                ], 500);
            }

            // Create a pending hire request
            $hireRequest = new HireRequest([
                'client_id' => $user->id,
                'tutor_id' => $educator->id,
                'topic' => $validated['topic'],
                'message' => $validated['message'] ?? '',
                'medium' => 'online',
                'duration' => $validated['duration'],
                'rate_per_session' => $amount,
                'currency' => $currency,
                'status' => 'pending',
                'payment_status' => 'pending',
                'transaction_reference' => $reference
            ]);
            $hireRequest->save();

            return response()->json([
                'message' => 'Payment initialized',
                'payment_url' => $initResponse['data']['data']['authorization_url'],
                'reference' => $initResponse['data']['data']['reference'],
                'hire_request' => $hireRequest
            ]);
        } catch (\Exception $e) {
            Log::error('Hire payment initialization failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'An error occurred while initializing payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify payment and complete the hire request
     */
    public function verifyPayment(Request $request)
    {
        $reference = $request->query('reference');
        
        if (!$reference) {
            return response()->json([
                'message' => 'No reference provided'
            ], 400);
        }

        try {
            DB::beginTransaction();
            
            // Find the hire request
            $hireRequest = HireRequest::where('transaction_reference', $reference)
                ->where('status', 'pending')
                ->first();
                
            if (!$hireRequest) {
                return response()->json([
                    'message' => 'Hire request not found'
                ], 404);
            }

            // Verify the payment
            $verification = $this->paystackService->verifyTransaction($reference);
            
            if (!$verification['success']) {
                return response()->json([
                    'message' => 'Payment verification failed',
                    'error' => $verification['message']
                ], 400);
            }
            
            $paymentData = $verification['data']['data'];
            
            // Check if payment was successful
            if ($paymentData['status'] !== 'success') {
                return response()->json([
                    'message' => 'Payment was not successful',
                    'status' => $paymentData['status']
                ], 400);
            }

            // Update the hire request
            $hireRequest->payment_status = 'paid';
            $hireRequest->save();

            // Notify the educator
            $educator = User::find($hireRequest->tutor_id);
            $client = User::find($hireRequest->client_id);
            $educator->notify(new HireRequestNotification($client, $hireRequest));

            DB::commit();

            return response()->json([
                'message' => 'Payment verified and hire request sent to educator',
                'hire_request' => $hireRequest
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment verification failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'An error occurred during payment verification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Educator accepts a hire request and initializes chat
     */
    public function acceptRequest($id)
    {
        try {
            DB::beginTransaction();
            
            $hireRequest = HireRequest::where('id', $id)
                ->where('tutor_id', auth()->id())
                ->where('status', 'pending')
                ->where('payment_status', 'paid')
                ->firstOrFail();

            $hireRequest->status = 'accepted';
            $hireRequest->save();
            
            // Get the tutor and client users
            $tutor = User::find($hireRequest->tutor_id);
            $client = User::find($hireRequest->client_id);
            
            // Create a conversation between tutor and client
            $conversation = $this->createConversationBetween($tutor, $client);
            
            // Send an initial message
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $tutor->id,
                'body' => "Hello! I've accepted your request to tutor you on: {$hireRequest->topic}. I'm looking forward to our sessions."
            ]);

            // Notify the client
            $client->notify(new HireRequestNotification($tutor, 'Your hire request has been accepted!'));

            DB::commit();

            return response()->json([
                'message' => 'Hire request accepted successfully',
                'conversation_id' => $conversation->id,
                'hire_request' => $hireRequest
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error accepting hire request: ' . $e->getMessage());
            return response()->json([
                'message' => 'An error occurred while accepting the request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * End a hiring session (one party)
     */
    public function initiateSessionEnd($id)
    {
        $hireRequest = HireRequest::findOrFail($id);
        
        // Check if user is associated with this request
        if (auth()->id() != $hireRequest->client_id && auth()->id() != $hireRequest->tutor_id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        
        // Check if the request is in an active state
        if ($hireRequest->status !== 'accepted') {
            return response()->json([
                'message' => 'This hire request is not in an active state'
            ], 400);
        }
        
        // Set the appropriate end flag
        if (auth()->id() == $hireRequest->client_id) {
            $hireRequest->client_end_requested = true;
            $otherUser = User::find($hireRequest->tutor_id);
        } else {
            $hireRequest->tutor_end_requested = true;
            $otherUser = User::find($hireRequest->client_id);
        }
        
        $hireRequest->save();
        
        // Check if both parties have requested to end
        if ($hireRequest->client_end_requested && $hireRequest->tutor_end_requested) {
            return $this->completeSession($id);
        }
        
        // Notify the other party
        $otherUser->notify(new HireRequestNotification(
            auth()->user(),
            'The other party has requested to end this tutoring session. Please confirm to complete it.'
        ));
        
        return response()->json([
            'message' => 'Session end requested. Waiting for other party to confirm.',
            'hire_request' => $hireRequest
        ]);
    }

    /**
     * Complete a session (when both parties have agreed)
     */
    private function completeSession($id)
    {
        try {
            DB::beginTransaction();
            
            $hireRequest = HireRequest::findOrFail($id);
            $hireRequest->status = 'completed';
            $hireRequest->session_ended_at = now();
            $hireRequest->save();
            
            // Archive the conversation
            $conversation = Conversation::where(function($query) use ($hireRequest) {
                $query->where('user_one_id', $hireRequest->client_id)
                      ->where('user_two_id', $hireRequest->tutor_id);
            })->orWhere(function($query) use ($hireRequest) {
                $query->where('user_one_id', $hireRequest->tutor_id)
                      ->where('user_two_id', $hireRequest->client_id);
            })->first();
            
            if ($conversation) {
                // We don't actually delete the conversation, just mark it as archived
                $conversation->is_archived = true;
                $conversation->save();
            }
            
            DB::commit();
            
            return response()->json([
                'message' => 'Session completed successfully',
                'hire_request' => $hireRequest
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error completing session: ' . $e->getMessage());
            return response()->json([
                'message' => 'An error occurred while completing the session',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a conversation between two users
     */
    private function createConversationBetween(User $userOne, User $userTwo)
    {
        // Check if a conversation already exists
        $conversation = Conversation::where(function($query) use ($userOne, $userTwo) {
            $query->where('user_one_id', $userOne->id)
                  ->where('user_two_id', $userTwo->id);
        })->orWhere(function($query) use ($userOne, $userTwo) {
            $query->where('user_one_id', $userTwo->id)
                  ->where('user_two_id', $userOne->id);
        })->first();
        
        // If no conversation exists, create a new one
        if (!$conversation) {
            $conversation = Conversation::create([
                'user_one_id' => $userOne->id,
                'user_two_id' => $userTwo->id,
                'last_message_at' => now()
            ]);
        } else {
            // If a conversation exists but was archived, unarchive it
            if ($conversation->is_archived) {
                $conversation->is_archived = false;
                $conversation->save();
            }
        }
        
        return $conversation;
    }
}