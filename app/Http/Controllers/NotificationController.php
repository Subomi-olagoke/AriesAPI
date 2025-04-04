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
        try {
            $user->notify(new BroadcastNotification(
                $request->title,
                $request->body,
                $request->data ?? []
            ));
            
            \Log::info('Notification sent to user', ['user_id' => $user->id]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Push notification sent successfully',
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to send notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send notification: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Debug APNs settings
     */
    public function debugApns()
    {
        // Get APNs config
        $config = config('services.apn');
        
        // Get users with device tokens
        $usersWithTokens = User::whereNotNull('device_token')->limit(5)->get(['id', 'device_token']);
        
        // Check if storage directory exists and is writable
        $storagePathExists = file_exists(storage_path('app'));
        $storagePathWritable = is_writable(storage_path('app'));
        
        // Check if p8 file exists (if using content)
        $p8FilePath = storage_path('app/apns_private_key.p8');
        $p8FileExists = file_exists($p8FilePath);
        $p8FileContents = $p8FileExists ? (strlen(file_get_contents($p8FilePath)) . ' bytes') : 'File not found';
        
        // Format the config for display (hide sensitive data)
        $safeConfig = array_merge($config, [
            'private_key_content' => $config['private_key_content'] ? 'Present (' . strlen($config['private_key_content']) . ' chars)' : 'Not set',
            'private_key_path' => $config['private_key_path'] ?? 'Not set',
        ]);
        
        return response()->json([
            'apns_config' => $safeConfig,
            'certificate_app_config' => config('services.apn.certificate.app') ?? 'Not configured',
            'storage_path_exists' => $storagePathExists,
            'storage_path_writable' => $storagePathWritable,
            'p8_file_exists' => $p8FileExists,
            'p8_file_contents_length' => $p8FileContents,
            'users_with_tokens_count' => $usersWithTokens->count(),
            'users_with_tokens_sample' => $usersWithTokens->map(function($user) {
                return [
                    'id' => $user->id,
                    'token_length' => strlen($user->device_token),
                    'token_preview' => substr($user->device_token, 0, 10) . '...' . substr($user->device_token, -10)
                ];
            }),
            'environment' => app()->environment(),
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
