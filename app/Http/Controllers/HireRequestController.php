<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\HireRequest;
use App\Models\TutoringSession;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\GoogleMeetService;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Notifications\HireRequestNotification;
use App\Notifications\SessionScheduledNotification;
use App\Notifications\PaymentRequiredNotification;
use Carbon\Carbon;

class HireRequestController extends Controller
{
    protected $googleMeetService;
    protected $paystackService;

    public function __construct(GoogleMeetService $googleMeetService, PaystackService $paystackService)
    {
        $this->googleMeetService = $googleMeetService;
        $this->paystackService = $paystackService;
    }

    /**
     * Send a hire request to a tutor
     */
    public function sendRequest(Request $request)
    {
        // Check if user has an active subscription
        if (!$this->hasActiveSubscription($request->user())) {
            return response()->json(['message' => 'You need an active subscription to hire tutors.'], 403);
        }

        // Validate the request
        $validated = $request->validate([
            'tutor_id' => 'required|exists:users,id',
            'topic' => 'required|string|max:255',
            'medium' => 'required|string|max:255',
            'duration' => 'required|string|max:255',
            'rate_per_session' => 'required|numeric|min:1',
            'currency' => 'required|string|in:NGN,USD,EUR,GBP',
        ]);

        $client = auth()->user();
        $client_id = auth()->id();

        // Prevent duplicate pending hire requests from the same client for the same tutor
        if (HireRequest::where([
            ['client_id', $client_id],
            ['tutor_id', $validated['tutor_id']],
            ['status', 'pending'],
        ])->exists()) {
            return response()->json(['message' => 'You have already sent a request to this tutor.'], 400);
        }

        // Create a new hire request
        $newHireReq = new HireRequest();
        $newHireReq->client_id = $client_id;
        $newHireReq->tutor_id = $validated['tutor_id'];
        $newHireReq->topic = $validated['topic'];
        $newHireReq->medium = $validated['medium'];
        $newHireReq->duration = $validated['duration'];
        $newHireReq->rate_per_session = $validated['rate_per_session'];
        $newHireReq->currency = $validated['currency'];
        $newHireReq->status = 'pending';
        $newHireReq->payment_status = 'unpaid';
        $newHireReq->save();

        // Build the message from the provided fields
        $message = "Topic: {$validated['topic']}\n"
                 . "Medium: {$validated['medium']}\n"
                 . "Duration: {$validated['duration']}\n"
                 . "Rate: {$validated['rate_per_session']} {$validated['currency']} per session";
        
        $newHireReq->message = $message;
        $newHireReq->save();

        // Notify the tutor about the new hire request
        $tutor = User::find($validated['tutor_id']);
        $tutor->notify(new HireRequestNotification($client, $message));

        return response()->json([
            'message' => 'Hire request sent successfully.',
            'hire_request' => $newHireReq
        ], 201);
    }

    /**
     * Accept a hire request
     */
    public function acceptRequest($id)
    {
        try {
            DB::beginTransaction();
            
            $hireRequest = HireRequest::where('id', $id)
                ->where('tutor_id', auth()->id())
                ->where('status', 'pending')
                ->firstOrFail();

            $hireRequest->update(['status' => 'accepted']);
            
            // Get the tutor and client users
            $tutor = User::find($hireRequest->tutor_id);
            $client = User::find($hireRequest->client_id);
            
            // Create a conversation between tutor and client
            $conversation = $this->createConversationBetween($tutor, $client);
            
            // Associate the conversation with the hire request
            $hireRequest->conversation_id = $conversation->id;
            $hireRequest->save();
            
            // Send an automatic first message
            $message = $this->sendInitialMessage($conversation, $tutor, $client, $hireRequest);

            DB::commit();

            return response()->json([
                'message' => 'Hire request accepted.',
                'conversation_id' => $conversation->id,
                'hire_request' => $hireRequest
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['message' => 'Hire request not found or already processed.'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error accepting hire request: ' . $e->getMessage());
            return response()->json(['message' => 'An error occurred while processing the request.'], 500);
        }
    }

    /**
     * Schedule a session for an accepted hire request
     */
    public function scheduleSession(Request $request, $id)
    {
        $request->validate([
            'scheduled_at' => 'required|date|after:now',
            'duration_minutes' => 'required|integer|min:15|max:300',
        ]);

        try {
            DB::beginTransaction();
            
            $hireRequest = HireRequest::where('id', $id)
                ->where(function($query) {
                    $query->where('tutor_id', auth()->id())
                          ->orWhere('client_id', auth()->id());
                })
                ->where('status', 'accepted')
                ->firstOrFail();
                
            $tutor = User::find($hireRequest->tutor_id);
            $client = User::find($hireRequest->client_id);
            
            // Calculate session start time
            $scheduledAt = Carbon::parse($request->scheduled_at);
            
            // Create Google Meet link
            $meetLink = $this->googleMeetService->createMeetLink(
                $tutor, 
                $client, 
                $hireRequest->topic, 
                $scheduledAt, 
                $request->duration_minutes
            );
            
            if (!$meetLink) {
                throw new \Exception('Failed to create Google Meet link');
            }
            
            // Create the tutoring session
            $session = new TutoringSession([
                'hire_request_id' => $hireRequest->id,
                'google_meet_link' => $meetLink,
                'scheduled_at' => $scheduledAt,
                'duration_minutes' => $request->duration_minutes,
                'status' => 'scheduled',
                'payment_status' => 'unpaid',
            ]);
            
            $session->save();
            
            // Update the hire request with scheduling info
            $hireRequest->update([
                'scheduled_at' => $scheduledAt,
                'google_meet_link' => $meetLink,
            ]);
            
            // Notify both parties
            $client->notify(new SessionScheduledNotification($session, $tutor));
            $tutor->notify(new SessionScheduledNotification($session, $client));
            
            // Notify client about payment
            $client->notify(new PaymentRequiredNotification($session));
            
            DB::commit();
            
            return response()->json([
                'message' => 'Session scheduled successfully',
                'session' => $session,
                'google_meet_link' => $meetLink,
                'payment_required' => true
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error scheduling session: ' . $e->getMessage());
            return response()->json(['message' => 'An error occurred while scheduling the session: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Process payment for a scheduled session
     */
    public function processPayment(Request $request, $sessionId)
    {
        $session = TutoringSession::findOrFail($sessionId);
        $hireRequest = $session->hireRequest;
        
        // Ensure only the client can pay
        if (auth()->id() !== $hireRequest->client_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        // Ensure the session isn't already paid
        if ($session->payment_status === 'paid') {
            return response()->json(['message' => 'This session has already been paid for'], 400);
        }
        
        try {
            DB::beginTransaction();
            
            $user = auth()->user();
            $amount = $hireRequest->rate_per_session;
            
            // Generate payment reference
            $reference = 'tutoring_' . $sessionId . '_' . uniqid();
            
            // Initialize payment with Paystack
            $result = $this->paystackService->initializeTransaction(
                $user->email,
                $amount,
                route('tutoring.payment.verify', ['session' => $sessionId]),
                [
                    'session_id' => $session->id,
                    'hire_request_id' => $hireRequest->id,
                    'payment_type' => 'tutoring_session'
                ]
            );
            
            if (!$result['success']) {
                throw new \Exception('Payment initialization failed: ' . ($result['message'] ?? 'Unknown error'));
            }
            
            // Update session with payment reference
            $session->update([
                'transaction_reference' => $result['data']['data']['reference']
            ]);
            
            // Create payment log
            \App\Models\PaymentLog::create([
                'user_id' => $user->id,
                'transaction_reference' => $result['data']['data']['reference'],
                'payment_type' => 'tutoring_session',
                'status' => 'pending',
                'amount' => $amount,
                'metadata' => json_encode([
                    'session_id' => $session->id,
                    'hire_request_id' => $hireRequest->id
                ])
            ]);
            
            DB::commit();
            
            return response()->json([
                'message' => 'Payment initialized',
                'payment_url' => $result['data']['data']['authorization_url'],
                'reference' => $result['data']['data']['reference']
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment initialization failed: ' . $e->getMessage());
            return response()->json(['message' => 'Payment initialization failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Verify payment for a tutoring session
     */
    public function verifyPayment(Request $request, $sessionId)
    {
        $session = TutoringSession::findOrFail($sessionId);
        $reference = $request->query('reference') ?? $session->transaction_reference;
        
        if (!$reference) {
            return response()->json(['message' => 'No payment reference provided'], 400);
        }
        
        try {
            DB::beginTransaction();
            
            // Verify the payment with Paystack
            $verification = $this->paystackService->verifyTransaction($reference);
            
            if (!$verification['success']) {
                throw new \Exception('Payment verification failed: ' . ($verification['message'] ?? 'Unknown error'));
            }
            
            $data = $verification['data']['data'];
            
            if ($data['status'] !== 'success') {
                throw new \Exception('Payment was not successful. Status: ' . $data['status']);
            }
            
            // Update payment log
            $paymentLog = \App\Models\PaymentLog::where('transaction_reference', $reference)->first();
            
            if ($paymentLog) {
                $paymentLog->update([
                    'status' => 'success',
                    'response_data' => array_merge($paymentLog->response_data ?? [], ['verification' => $data])
                ]);
            }
            
            // Update session and hire request
            $session->update([
                'payment_status' => 'paid'
            ]);
            
            // If this is the first session, update the hire request payment status too
            if ($session->hireRequest->sessions()->count() === 1) {
                $session->hireRequest->update([
                    'payment_status' => 'paid'
                ]);
            }
            
            DB::commit();
            
            return response()->json([
                'message' => 'Payment verified successfully',
                'session' => $session->fresh()
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment verification failed: ' . $e->getMessage());
            return response()->json(['message' => 'Payment verification failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Complete a tutoring session
     */
    public function completeSession(Request $request, $sessionId)
    {
        $request->validate([
            'feedback_rating' => 'nullable|integer|min:1|max:5',
            'feedback_comment' => 'nullable|string|max:1000',
        ]);

        $session = TutoringSession::findOrFail($sessionId);
        $hireRequest = $session->hireRequest;
        
        // Ensure only the participants can mark it complete
        if (auth()->id() !== $hireRequest->client_id && auth()->id() !== $hireRequest->tutor_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        // Only complete sessions that are in progress or scheduled and paid
        if ($session->status !== 'in_progress' && !($session->status === 'scheduled' && $session->payment_status === 'paid')) {
            return response()->json(['message' => 'Session cannot be completed in its current state'], 400);
        }
        
        try {
            $session->update([
                'status' => 'completed',
                'ended_at' => now(),
                'feedback_rating' => $request->feedback_rating,
                'feedback_comment' => $request->feedback_comment,
            ]);
            
            // Notify the other party
            $otherUser = auth()->id() === $hireRequest->client_id 
                ? User::find($hireRequest->tutor_id) 
                : User::find($hireRequest->client_id);
                
            // You would create and use SessionCompletedNotification here
            
            return response()->json([
                'message' => 'Session marked as completed',
                'session' => $session->fresh()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error completing session: ' . $e->getMessage());
            return response()->json(['message' => 'An error occurred while completing the session'], 500);
        }
    }

    /**
     * Decline a hire request
     */
    public function declineRequest($id)
    {
        $hireRequest = HireRequest::where('id', $id)
            ->where('tutor_id', auth()->id())
            ->where('status', 'pending')
            ->firstOrFail();

        $hireRequest->update(['status' => 'declined']);

        return response()->json(['message' => 'Hire request declined.']);
    }

    /**
     * Cancel a hire request
     */
    public function cancelRequest($id)
    {
        try {
            $hireRequest = HireRequest::where('id', $id)
                ->where('client_id', auth()->id())
                ->where('status', 'pending')
                ->firstOrFail();

            $hireRequest->delete();

            return response()->json(['message' => 'Hire request canceled.'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Hire request not found or already processed.'], 404);
        }
    }

    /**
     * List all hire requests for the current user
     */
    public function listRequests()
    {
        $user = auth()->user();

        $requests = HireRequest::where('tutor_id', $user->id)
            ->orWhere('client_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->with(['client', 'tutor', 'sessions'])
            ->get();

        if ($requests->isEmpty()) {
            return response()->json(['message' => 'No hire requests found.'], 404);
        }

        return response()->json($requests, 200);
    }
    
    /**
     * Get a specific hire request with its sessions
     */
    public function getRequest($id)
    {
        $user = auth()->user();

        $request = HireRequest::where('id', $id)
            ->where(function($query) use ($user) {
                $query->where('tutor_id', $user->id)
                    ->orWhere('client_id', $user->id);
            })
            ->with(['client', 'tutor', 'sessions'])
            ->firstOrFail();

        return response()->json($request, 200);
    }

    /**
     * Create a conversation between tutor and client
     */
    private function createConversationBetween(User $tutor, User $client) 
    {
        // Check if a conversation already exists
        $conversation = Conversation::where(function ($query) use ($tutor, $client) {
            $query->where('user_one_id', $tutor->id)
                  ->where('user_two_id', $client->id);
        })->orWhere(function ($query) use ($tutor, $client) {
            $query->where('user_one_id', $client->id)
                  ->where('user_two_id', $tutor->id);
        })->first();
        
        // If no conversation exists, create a new one
        if (!$conversation) {
            $conversation = Conversation::create([
                'user_one_id' => $tutor->id,
                'user_two_id' => $client->id,
                'last_message_at' => now(),
            ]);
        }
        
        return $conversation;
    }
    
    /**
     * Send initial message in the conversation
     */
    private function sendInitialMessage(Conversation $conversation, User $tutor, User $client, HireRequest $hireRequest) 
    {
        // Create a welcome message from the tutor
        $welcomeMessage = "Hello {$client->first_name}, thank you for hiring me as your tutor. " .
                         "I'm looking forward to helping you with: \n\n{$hireRequest->message}\n\n" .
                         "Let's schedule our first session. You can use the 'Schedule Session' button to propose a time.";
                         
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $tutor->id,
            'body' => $welcomeMessage,
        ]);
        
        // Update the conversation's last_message_at
        $conversation->update(['last_message_at' => now()]);
        
        return $message;
    }
    
    /**
     * Check if user has an active subscription
     */
    private function hasActiveSubscription(User $user)
    {
        // Implement real subscription checking here
        // For now, we'll return true for testing
        $subscription = \App\Models\Subscription::where('user_id', $user->id)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();
            
        return $subscription !== null;
    }
}