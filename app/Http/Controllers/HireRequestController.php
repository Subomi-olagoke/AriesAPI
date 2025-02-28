<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\HireRequest;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use App\Notifications\HireRequestNotification;

class HireRequestController extends Controller
{
    public function sendRequest(Request $request) {
        // Check if user has an active subscription
        if (!$this->hasActiveSubscription($request->user())) {
            return response()->json(['message' => 'You need an active subscription to hire tutors.'], 403);
        }

        // Validate that the request contains a tutor_id and the custom fields.
        $validated = $request->validate([
            'tutor_id' => 'required|exists:users,id',
            'topic'    => 'required|string|max:255',
            'medium'   => 'required|string|max:255',
            'duration' => 'required|string|max:255',
        ]);

        $client = auth()->user();
        $client_id = auth()->id();

        // Prevent duplicate pending hire requests from the same client for the same tutor.
        if (HireRequest::where([
            ['client_id', $client_id],
            ['tutor_id', $validated['tutor_id']],
            ['status', 'pending'],
        ])->exists()) {
            return response()->json(['message' => 'You have already sent a request to this tutor.'], 400);
        }

        // Build the custom message from the provided fields.
        $customMessage = "Topic: {$validated['topic']}\n"
                       . "Medium: {$validated['medium']}\n"
                       . "Duration: {$validated['duration']}";

        // Create a new hire request using the custom message.
        $newHireReq = new HireRequest();
        $newHireReq->client_id = $client_id;
        $newHireReq->tutor_id  = $validated['tutor_id'];
        $newHireReq->message   = $customMessage;
        $newHireReq->save();

        // Notify the tutor about the new hire request with the custom message.
        $tutor = User::find($validated['tutor_id']);
        $tutor->notify(new HireRequestNotification($client, $customMessage));

        return response()->json([
            'message'      => 'Hire request sent successfully.',
            'hire_request' => $newHireReq
        ], 201);
    }

    public function acceptRequest($id) {
        try {
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
            
            // Send an automatic first message
            $message = $this->sendInitialMessage($conversation, $tutor, $client, $hireRequest);

            return response()->json([
                'message' => 'Hire request accepted.',
                'conversation_id' => $conversation->id 
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Hire request not found or already processed.'], 404);
        }
    }

    public function declineRequest($id){
        $hireRequest = HireRequest::where('id', $id)
            ->where('tutor_id', auth()->id())
            ->where('status', 'pending')
            ->firstOrFail();

        $hireRequest->update(['status' => 'declined']);

        return response()->json(['message' => 'Hire request declined.']);
    }

    public function cancelRequest($id) {
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

    public function listRequests() {
        $user = auth()->user();

        $requests = HireRequest::where('tutor_id', $user->id)
            ->orWhere('client_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->with('client', 'tutor')
            ->get();

        if ($requests->isEmpty()) {
            return response()->json(['message' => 'No hire requests found.'], 404);
        }

        return response()->json($requests, 200);
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
                         "Let's discuss how we'll proceed with your learning goals.";
                         
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
        // This is a placeholder that returns true for now (0 payment)
        // Later this will verify an actual subscription
        return true;
    }
}