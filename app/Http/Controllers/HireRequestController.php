<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\HireRequest;
use Illuminate\Http\Request;
use App\Notifications\HireRequestNotification;

class HireRequestController extends Controller
{
    public function sendRequest(Request $request) {
    $validated = $request->validate([
        'tutor_id' => 'required|exists:users,id',
        'message' => 'nullable|string|max:1000',
    ]);

    $client = auth()->user();

    $client_id = auth()->id();

    if (HireRequest::where([
        ['client_id', $client_id],
        ['tutor_id', $validated['tutor_id']],
        ['status', 'pending'],
    ])->exists()) {
        return response()->json(['message' => 'You have already sent a request to this tutor.'], 400);
    }

    HireRequest::create([
        'client_id' => $client_id,
        'tutor_id' => $validated['tutor_id'],
        'message' => $validated['message'] ?? null,
    ]);

    $tutor = User::find($validated['tutor_id']);
    $tutor->notify(new HireRequestNotification($client, $validated['message'] ?? null));

    return response()->json(['message' => 'Hire request sent successfully.'], 201);

    }

    public function acceptRequest($id){
    $request = HireRequest::where('id', $id)
        ->where('tutor_id', auth()->id())
        ->where('status', 'pending')
        ->firstOrFail();

    $request->update(['status' => 'accepted']);

    return response()->json(['message' => 'Hire request accepted.']);
    }

    public function declineRequest($id){
    $request = HireRequest::where('id', $id)
        ->where('tutor_id', auth()->id())
        ->where('status', 'pending')
        ->firstOrFail();

    $request->update(['status' => 'declined']);

    return response()->json(['message' => 'Hire request declined.']);

    }

    public function cancelRequest($id) {
        $request = HireRequest::where('id', $id)
            ->where('client_id', auth()->id())
            ->where('status', 'pending')
            ->firstOrFail();

            if (!$request) {
                return response()->json(['message' => 'No pending hire request found to cancel.'], 404);
            }

        $request->delete();

        return response()->json(['message' => 'Hire request canceled.'], 200);
    }

    public function listRequests() {
        $user = auth()->user();

        $requests = HireRequest::where('tutor_id', $user->id)
            ->orWhere('client_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

            if ($requests->isEmpty()) {
                return response()->json(['message' => 'No hire requests found.'], 404);
            }

        return response()->json($requests, 200);
    }

}
