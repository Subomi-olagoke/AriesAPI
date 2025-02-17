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

}
