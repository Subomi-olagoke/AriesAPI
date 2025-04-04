<?php

namespace App\Http\Controllers;

use App\Models\Notification as NotificationModel;
use App\Models\User;
use App\Notifications\BroadcastNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class NotificationController extends Controller
{

    public function getNotifications(Request $request) {
        return response()->json([
            'notifications' => $request->user()->unreadNotifications
        ]);
    }

    public function markAsRead($id) {
        $notification = auth()->user()->notifications()->find($id);
        if ($notification) {
            $notification->markAsRead();
        }
        return response()->json(['message' => 'Notification marked as read']);
    }
    
    /**
     * Send a broadcast push notification to all users with device tokens.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function broadcastPushNotification(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'data' => 'nullable|array',
        ]);

        // Get all users with device tokens
        $users = User::whereNotNull('device_token')->get();

        if ($users->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No users with registered devices found',
            ], 404);
        }

        // Create a new broadcast notification
        Notification::send($users, new BroadcastNotification(
            $request->title,
            $request->body,
            $request->data ?? []
        ));

        return response()->json([
            'status' => 'success',
            'message' => 'Push notification broadcast sent successfully',
            'recipients_count' => $users->count(),
        ]);
    }

    /**
     * Send a push notification to a specific user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function sendPushNotification(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'data' => 'nullable|array',
        ]);

        $user = User::find($request->user_id);

        if (!$user->device_token) {
            return response()->json([
                'status' => 'error',
                'message' => 'User has no registered device',
            ], 404);
        }

        // Send notification to specific user
        $user->notify(new BroadcastNotification(
            $request->title,
            $request->body,
            $request->data ?? []
        ));

        return response()->json([
            'status' => 'success',
            'message' => 'Push notification sent successfully',
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Notification $notification)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Notification $notification)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Notification $notification)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Notification $notification)
    {
        //
    }
}
