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

        $user = Auth::user();
        $user->device_token = $request->device_token;
        $user->device_type = $request->device_type;
        $user->save();

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