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
     * Send a push notification to a specific user using direct APNs package access.
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
            // Try sending through standard notification system
            $user->notify(new BroadcastNotification(
                $request->title,
                $request->body,
                $request->data ?? []
            ));
            
            Log::info('Notification sent to user through standard channel', ['user_id' => $user->id]);
            
            // Now try a direct APNs approach as well
            $this->sendDirectAppleNotification($user, $request->title, $request->body, $request->data ?? []);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Push notification sent successfully using both methods',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Try the direct method if standard notification failed
            try {
                $this->sendDirectAppleNotification($user, $request->title, $request->body, $request->data ?? []);
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'Push notification sent successfully using direct method',
                ]);
            } catch (\Exception $directException) {
                Log::error('Failed to send direct notification', [
                    'error' => $directException->getMessage(),
                    'trace' => $directException->getTraceAsString()
                ]);
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to send notification: ' . $e->getMessage() . 
                                 ' Direct method also failed: ' . $directException->getMessage(),
                ], 500);
            }
        }
    }
    
    /**
     * Send a direct notification to Apple's APNS service bypassing Laravel's notification system
     */
    protected function sendDirectAppleNotification(User $user, string $title, string $body, array $data = [])
    {
        if ($user->device_type !== 'ios' || empty($user->device_token)) {
            Log::warning('Cannot send direct Apple notification', [
                'reason' => 'User has no iOS device or token',
                'device_type' => $user->device_type,
                'has_token' => !empty($user->device_token)
            ]);
            return false;
        }
        
        try {
            // Get config from environment variables directly
            $keyId = env('APNS_KEY_ID');
            $teamId = env('APNS_TEAM_ID');
            $appBundleId = env('APNS_APP_BUNDLE_ID');
            $production = env('APNS_PRODUCTION', false);
            
            // Create p8 file if needed
            $privateKeyPath = storage_path('app/direct_apns_key.p8');
            $privateKeyContent = env('APNS_PRIVATE_KEY_CONTENT');
            
            if (!empty($privateKeyContent)) {
                $keyContent = base64_decode($privateKeyContent);
                if (!file_exists($privateKeyPath) || md5_file($privateKeyPath) !== md5($keyContent)) {
                    file_put_contents($privateKeyPath, $keyContent);
                    chmod($privateKeyPath, 0600); // Ensure proper permissions
                }
                
                Log::info('Created direct p8 file', [
                    'path' => $privateKeyPath,
                    'size' => filesize($privateKeyPath),
                    'permissions' => substr(sprintf('%o', fileperms($privateKeyPath)), -4)
                ]);
            } else {
                Log::error('Cannot create direct p8 file - no key content available');
                return false;
            }
            
            // Log the direct notification attempt
            Log::info('Attempting direct APNs notification', [
                'device_token' => $user->device_token,
                'key_id' => $keyId,
                'team_id' => $teamId,
                'app_bundle_id' => $appBundleId,
                'p8_file_exists' => file_exists($privateKeyPath),
                'production' => $production
            ]);
            
            // Create a new client
            $options = [
                'key_id' => $keyId,
                'team_id' => $teamId,
                'app_bundle_id' => $appBundleId,
                'private_key_path' => $privateKeyPath,
                'production' => $production
            ];
            
            // Use the package's classes directly
            $client = new \NotificationChannels\Apn\ClientFactory(app(), $options);
            
            // Create message
            $alert = [
                'title' => $title,
                'body' => $body,
            ];
            
            $payload = [
                'aps' => [
                    'alert' => $alert,
                    'badge' => 1,
                    'sound' => 'default',
                    'content-available' => 1,
                    'mutable-content' => 1,
                ],
            ];
            
            // Add custom data
            if (!empty($data)) {
                $payload['custom_data'] = $data;
            }
            
            // Send directly
            $response = $client->push($user->device_token, $payload, $production);
            
            Log::info('Direct APNs notification sent', ['response' => $response]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send direct APNs notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
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
        
        // Check direct p8 file
        $directP8FilePath = storage_path('app/direct_apns_key.p8');
        $directP8FileExists = file_exists($directP8FilePath);
        $directP8FileContents = $directP8FileExists ? (strlen(file_get_contents($directP8FilePath)) . ' bytes') : 'File not found';
        
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
            'certificate_app_config' => config('services.apn.certificate.app') ?? 'Not configured',
            'storage_path_exists' => $storagePathExists,
            'storage_path_writable' => $storagePathWritable,
            'p8_file_exists' => $p8FileExists,
            'p8_file_contents_length' => $p8FileContents,
            'direct_p8_file_exists' => $directP8FileExists,
            'direct_p8_file_contents_length' => $directP8FileContents,
            'users_with_tokens_count' => $usersWithTokens->count(),
            'users_with_tokens_sample' => $usersWithTokens->map(function($user) {
                return [
                    'id' => $user->id,
                    'token_length' => strlen($user->device_token),
                    'token_preview' => substr($user->device_token, 0, 10) . '...' . substr($user->device_token, -10)
                ];
            }),
            'environment' => app()->environment(),
            'package_version' => \Composer\InstalledVersions::getVersion('laravel-notification-channels/apn') ?? 'unknown',
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