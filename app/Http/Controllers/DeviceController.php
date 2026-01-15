<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeviceController extends Controller
{
    /**
     * Register or update a device token for push notifications
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function registerDevice(Request $request)
    {
        $request->validate([
            'device_token' => 'required|string',
            'device_type' => 'required|in:ios,android,web',
        ]);

        $deviceToken = $request->device_token;
        $deviceType = $request->device_type;
        
        // Sanitize token - remove any non-hex characters for iOS tokens
        if ($deviceType === 'ios') {
            // Check if token contains non-hex characters or spaces
            if (!ctype_xdigit(str_replace(' ', '', $deviceToken))) {
                // Remove any non-hex characters
                $cleanToken = preg_replace('/[^0-9a-fA-F]/', '', $deviceToken);
                
                // Log the token transformation
                \Log::info('Transformed iOS device token', [
                    'original' => $deviceToken,
                    'sanitized' => $cleanToken,
                    'original_length' => strlen($deviceToken),
                    'new_length' => strlen($cleanToken)
                ]);
                
                $deviceToken = $cleanToken;
            }
        }
        
        // Check token length for iOS (should be 64 characters for prod)
        if ($deviceType === 'ios' && strlen($deviceToken) !== 64) {
            \Log::warning('Suspicious iOS token length', [
                'token' => $deviceToken,
                'length' => strlen($deviceToken),
                'expected' => 64
            ]);
        }
        
        $user = Auth::user();
        $user->device_token = $deviceToken;
        $user->device_type = $deviceType;
        $user->save();
        
        // Log registration success
        \Log::info('Device registered', [
            'user_id' => $user->id,
            'device_type' => $deviceType,
            'token_length' => strlen($deviceToken)
        ]);

        return response()->json([
            'message' => 'Device registered successfully',
            'device_type' => $user->device_type
        ]);
    }

    /**
     * Unregister a device (remove token)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function unregisterDevice()
    {
        $user = Auth::user();
        $user->device_token = null;
        $user->device_type = null;
        $user->save();

        return response()->json([
            'message' => 'Device unregistered successfully'
        ]);
    }
}