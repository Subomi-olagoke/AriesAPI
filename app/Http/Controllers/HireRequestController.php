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
     * Send a request to hire an educator
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendRequest(Request $request)
    {
        $validated = $request->validate([
            'tutor_id' => 'required|uuid|exists:users,id',
            'topic' => 'required|string|max:255',
            'message' => 'nullable|string',
            'medium' => 'nullable|string',
            'duration' => 'nullable|string',
        ]);
        
        try {
            $client = auth()->user();
            $tutor = User::findOrFail($validated['tutor_id']);
            
            // Check if tutor is an educator
            if ($tutor->role !== User::ROLE_EDUCATOR) {
                return response()->json([
                    'message' => 'The selected user is not an educator'
                ], 400);
            }
            
            // Get the educator's hire rate
            $profile = $tutor->profile;
            if (!$profile || !$profile->hire_rate) {
                return response()->json([
                    'message' => 'This educator has not set up their hiring rate yet'
                ], 400);
            }
            
            $baseRate = $profile->hire_rate;
            $currency = $profile->hire_currency ?? 'USD';
            
            // Parse duration to calculate total hours
            $duration = $validated['duration'] ?? '1 hour';
            $hours = $this->calculateHoursFromDuration($duration);
            
            // Calculate total amount (base rate * hours)
            $totalAmount = $baseRate * $hours;
            
            // Use the original amount for Paystack (no conversion)
            $paystackAmount = $totalAmount;
            
            // Generate payment reference
            $reference = 'hire_' . uniqid() . '_' . time();
            
            // Initialize payment with Paystack
            $initResponse = $this->paystackService->initializeTransaction(
                $client->email,
                $paystackAmount, // Amount in Naira for Paystack
                route('hire.payment.verify'),
                [
                    'educator_id' => $tutor->id,
                    'user_id' => $client->id,
                    'topic' => $validated['topic'],
                    'duration' => $duration,
                    'message' => $validated['message'] ?? '',
                    'medium' => $validated['medium'] ?? 'online',
                    'payment_type' => 'educator_hire',
                    'base_rate' => $baseRate,
                    'hours' => $hours,
                    'total_amount' => $totalAmount,
                    'currency' => $currency
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
                'client_id' => $client->id,
                'tutor_id' => $tutor->id,
                'status' => 'pending',
                'topic' => $validated['topic'],
                'message' => $validated['message'] ?? '',
                'medium' => $validated['medium'] ?? 'online',
                'duration' => $duration,
                'rate_per_session' => $totalAmount, // Store the total amount in original currency
                'currency' => $currency,
                'payment_status' => 'pending',
                'transaction_reference' => $reference
            ]);
            
            $hireRequest->save();
            
            // Notify the educator about the session request (even if payment might fail later)
            $educator = User::find($tutor->id);
            $educator->notify(new HireRequestNotification(
                $client, 
                "A user has requested a session with you on the topic: {$validated['topic']}. Payment is being processed."
            ));
            
            return response()->json([
                'message' => 'Hire request created and payment initialized',
                'payment_url' => $initResponse['data']['data']['authorization_url'],
                'reference' => $initResponse['data']['data']['reference'],
                'hire_request' => $hireRequest
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Error sending hire request: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'An error occurred while sending the request',
                'error' => $e->getMessage()
            ], 500);
        }
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
            
            // Notify the educator about the session request (even if payment might fail later)
            $educator->notify(new HireRequestNotification(
                $user, 
                "A user has requested a session with you on the topic: {$validated['topic']}. Payment is being processed."
            ));

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

            // Notify the educator that payment was successful
            $educator = User::find($hireRequest->tutor_id);
            $client = User::find($hireRequest->client_id);
            $educator->notify(new HireRequestNotification(
                $client, 
                "Payment for session on '{$hireRequest->topic}' has been successfully processed. You can now accept or decline this request."
            ));

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
                ->firstOrFail(); // Removed payment_status check to allow accepting with pending payments
            
            // Add a warning for pending payments
            $paymentWarning = '';
            if ($hireRequest->payment_status !== 'paid') {
                $paymentWarning = " Note: Payment is still pending for this request.";
            }

            $hireRequest->status = 'accepted';
            $hireRequest->save();
            
            // Get the tutor and client users
            $tutor = User::find($hireRequest->tutor_id);
            $client = User::find($hireRequest->client_id);
            
            // Create a conversation between tutor and client
            $conversation = $this->createConversationBetween($tutor, $client);
            
            // Send an initial message
            $messageBody = "Hello! I've accepted your request to tutor you on: {$hireRequest->topic}. I'm looking forward to our sessions.";
            
            // Add payment warning to message if payment is pending
            if ($hireRequest->payment_status !== 'paid') {
                $messageBody .= " Please note that your payment is still pending. Our session will be confirmed once payment is completed.";
            }
            
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $tutor->id,
                'body' => $messageBody
            ]);

            // Notify the client
            $client->notify(new HireRequestNotification(
                $tutor, 
                'Your hire request has been accepted!' . $paymentWarning
            ));

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
     * Decline a hire request as an educator
     */
    public function declineRequest($id)
    {
        try {
            DB::beginTransaction();
            
            $hireRequest = HireRequest::where('id', $id)
                ->where('tutor_id', auth()->id())
                ->where('status', 'pending')
                ->firstOrFail();

            $hireRequest->status = 'declined';
            $hireRequest->save();
            
            // Get the client user
            $client = User::find($hireRequest->client_id);
            
            // Notify the client
            $client->notify(new HireRequestNotification(
                auth()->user(),
                'Your hire request has been declined.'
            ));

            DB::commit();

            return response()->json([
                'message' => 'Hire request declined successfully',
                'hire_request' => $hireRequest
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error declining hire request: ' . $e->getMessage());
            return response()->json([
                'message' => 'An error occurred while declining the request',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Cancel a hire request as a client
     */
    public function cancelRequest($id)
    {
        try {
            $hireRequest = HireRequest::where('id', $id)
                ->where('client_id', auth()->id())
                ->whereIn('status', ['pending', 'accepted'])
                ->firstOrFail();
            
            // If the request has been paid for, we might need special handling
            if ($hireRequest->payment_status === 'paid') {
                // Here you would implement refund logic if needed
                // For now, we'll just log that the user cancelled a paid request
                Log::info('User cancelled a paid hire request: ' . $hireRequest->id);
            }
            
            $hireRequest->status = 'cancelled';
            $hireRequest->save();
            
            // Get the tutor user
            $tutor = User::find($hireRequest->tutor_id);
            
            // Notify the tutor
            $tutor->notify(new HireRequestNotification(
                auth()->user(),
                'A hire request has been cancelled.'
            ));
            
            return response()->json([
                'message' => 'Hire request cancelled successfully',
                'hire_request' => $hireRequest
            ]);
        } catch (\Exception $e) {
            Log::error('Error cancelling hire request: ' . $e->getMessage());
            return response()->json([
                'message' => 'An error occurred while cancelling the request',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * List hire requests for the current user
     */
    public function listRequests(Request $request)
    {
        $user = auth()->user();
        $type = $request->query('type', 'all'); // all, sent, received
        $status = $request->query('status'); // pending, accepted, declined, cancelled, completed
        
        $query = HireRequest::query();
        
        // Filter by user role
        if ($type === 'sent' || ($type === 'all' && $user->role !== User::ROLE_EDUCATOR)) {
            $query->where('client_id', $user->id);
        } elseif ($type === 'received' || ($type === 'all' && $user->role === User::ROLE_EDUCATOR)) {
            $query->where('tutor_id', $user->id);
        } else {
            // If type is 'all' and the user could be both client and tutor
            $query->where(function($q) use ($user) {
                $q->where('client_id', $user->id)
                  ->orWhere('tutor_id', $user->id);
            });
        }
        
        // Filter by status if provided
        if ($status) {
            $query->where('status', $status);
        }
        
        // Get requests with user info
        $hireRequests = $query->with(['client', 'tutor'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'hire_requests' => $hireRequests
        ]);
    }
    
    /**
     * Get a specific hire request
     */
    public function getRequest($id)
    {
        $user = auth()->user();
        
        $hireRequest = HireRequest::where('id', $id)
            ->where(function($query) use ($user) {
                $query->where('client_id', $user->id)
                    ->orWhere('tutor_id', $user->id);
            })
            ->with(['client', 'tutor'])
            ->firstOrFail();
        
        return response()->json([
            'hire_request' => $hireRequest
        ]);
    }
    
    /**
     * Schedule a tutoring session
     */
    public function scheduleSession(Request $request, $id)
    {
        $validated = $request->validate([
            'scheduled_at' => 'required|date|after:now',
            'google_meet_link' => 'nullable|url',
        ]);
        
        try {
            DB::beginTransaction();
            
            $hireRequest = HireRequest::where('id', $id)
                ->where(function($query) {
                    $query->where('client_id', auth()->id())
                        ->orWhere('tutor_id', auth()->id());
                })
                ->where('status', 'accepted')
                ->firstOrFail();
            
            $hireRequest->scheduled_at = $validated['scheduled_at'];
            
            if (isset($validated['google_meet_link'])) {
                $hireRequest->google_meet_link = $validated['google_meet_link'];
            }
            
            $hireRequest->save();
            
            // Determine the other party
            $otherUserId = (auth()->id() == $hireRequest->client_id) 
                ? $hireRequest->tutor_id 
                : $hireRequest->client_id;
            
            $otherUser = User::find($otherUserId);
            
            // Notify the other party
            $otherUser->notify(new HireRequestNotification(
                auth()->user(),
                'A tutoring session has been scheduled.'
            ));
            
            DB::commit();
            
            return response()->json([
                'message' => 'Session scheduled successfully',
                'hire_request' => $hireRequest
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error scheduling session: ' . $e->getMessage());
            return response()->json([
                'message' => 'An error occurred while scheduling the session',
                'error' => $e->getMessage()
            ], 500);
        }
    }

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
     * Calculate the number of hours from a duration string
     * 
     * @param string $duration Duration string (e.g., "1 hour", "2 hours", "3 days", "4 weeks")
     * @return float Number of hours
     */
    private function calculateHoursFromDuration($duration)
    {
        // Default to 1 hour if parsing fails
        $hours = 1;
        
        // Try to extract the numeric value and unit
        if (preg_match('/(\d+)\s*(\w+)/', $duration, $matches)) {
            $value = (int) $matches[1];
            $unit = strtolower(rtrim($matches[2], 's')); // Remove trailing 's' to handle plurals
            
            switch ($unit) {
                case 'hour':
                    $hours = $value;
                    break;
                case 'day':
                    $hours = $value * 24; // Assuming 24 hours per day
                    break;
                case 'week':
                    $hours = $value * 24 * 7; // Assuming 7 days per week
                    break;
                case 'month':
                    $hours = $value * 24 * 30; // Assuming 30 days per month
                    break;
                default:
                    $hours = $value; // Default to treating as hours
            }
        }
        
        return $hours;
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