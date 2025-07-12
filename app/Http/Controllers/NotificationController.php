<?php

namespace App\Http\Controllers;

use App\Models\Notification as NotificationModel;
use App\Models\User;
use App\Notifications\BroadcastNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\Apn\ApnChannel;
use NotificationChannels\Apn\ApnMessage;
use Illuminate\Support\Facades\Log;

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

        try {
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
        } catch (\Exception $e) {
            Log::error('Failed to broadcast notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send broadcast notification: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send a push notification to a specific user using standard notification system.
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
        
        // Log token details for debugging
        Log::info('About to send notification', [
            'user_id' => $user->id,
            'device_token' => $user->device_token,
            'token_length' => strlen($user->device_token),
            'token_format' => ctype_xdigit($user->device_token) ? 'hexadecimal' : 'non-hexadecimal'
        ]);
        
        try {
            // Send through standard notification system
            $user->notify(new BroadcastNotification(
                $request->title,
                $request->body,
                $request->data ?? []
            ));
            
            Log::info('Notification sent to user successfully', ['user_id' => $user->id]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Push notification sent successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send push notification: ' . $e->getMessage(),
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
        $safeConfig = array_merge($config ?? [], [
            'key_id' => env('APNS_KEY_ID'),
            'team_id' => env('APNS_TEAM_ID'),
            'app_bundle_id' => env('APNS_APP_BUNDLE_ID'),
            'private_key_content' => env('APNS_PRIVATE_KEY_CONTENT') ? 'Present (' . strlen(env('APNS_PRIVATE_KEY_CONTENT')) . ' chars)' : 'Not set',
            'private_key_path' => env('APNS_PRIVATE_KEY_PATH') ?? 'Not set',
            'production' => env('APNS_PRODUCTION', false),
        ]);
        
        return response()->json([
            'apns_config' => $safeConfig,
            'users_with_tokens' => $usersWithTokens->count(),
            'sample_users' => $usersWithTokens->map(function($user) {
                return [
                    'id' => $user->id,
                    'token_length' => strlen($user->device_token),
                    'token_format' => ctype_xdigit($user->device_token) ? 'hexadecimal' : 'non-hexadecimal'
                ];
            }),
            'storage' => [
                'path_exists' => $storagePathExists,
                'path_writable' => $storagePathWritable,
                'p8_file_exists' => $p8FileExists,
                'p8_file_contents' => $p8FileContents
            ],
            'environment' => [
                'app_env' => env('APP_ENV'),
                'app_debug' => env('APP_DEBUG'),
                'queue_connection' => env('QUEUE_CONNECTION')
            ]
        ]);
    }
    
    /**
     * Test notification endpoint
     */
    public function testNotification(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::find($request->user_id);
        
        if (!$user->device_token) {
            return response()->json([
                'status' => 'error',
                'message' => 'User has no device token registered'
            ], 404);
        }

        try {
            // Send a test notification
            $user->notify(new BroadcastNotification(
                'Test Notification',
                'This is a test notification from the backend',
                ['type' => 'test', 'timestamp' => now()->toISOString()]
            ));
            
            return response()->json([
                'status' => 'success',
                'message' => 'Test notification sent successfully',
                'user_id' => $user->id,
                'device_token_length' => strlen($user->device_token)
            ]);
        } catch (\Exception $e) {
            Log::error('Test notification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Test notification failed: ' . $e->getMessage()
            ], 500);
        }
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
    public function show(NotificationModel $notification)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(NotificationModel $notification)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, NotificationModel $notification)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(NotificationModel $notification)
    {
        //
    }
}