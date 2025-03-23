<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Google_Client;

class GoogleController extends Controller
{
    /**
     * Authenticate a user with a Google ID token from mobile app.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function authenticateWithGoogle(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        try {
            // Initialize the Google client
            $client = new Google_Client(['client_id' => config('services.google.client_id')]);
            
            // Verify the ID token
            $payload = $client->verifyIdToken($request->id_token);
            
            if (!$payload) {
                return response()->json([
                    'message' => 'Invalid ID token'
                ], 401);
            }
            
            // Get user data from payload
            $googleId = $payload['sub'];
            $email = $payload['email'];
            $firstName = $payload['given_name'] ?? '';
            $lastName = $payload['family_name'] ?? '';
            $name = $payload['name'] ?? "{$firstName} {$lastName}";
            $avatar = $payload['picture'] ?? null;
            
            // Find or create user
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                $user = new User();
                $user->username = $this->generateUsername($name);
                $user->first_name = $firstName;
                $user->last_name = $lastName;
                $user->email = $email;
                $user->password = Hash::make(Str::random(16));
                $user->email_verified_at = now();
                $user->avatar = $avatar;
                $user->save();
            }
            
            // Revoke all existing tokens
            $user->tokens()->delete();
            
            // Create new token
            $token = $user->createToken('google-auth-mobile')->plainTextToken;
            
            return response()->json([
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Google authentication failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Generate a unique username from the name.
     *
     * @param string $name
     * @return string
     */
    private function generateUsername(string $name): string
    {
        // Convert name to lowercase and replace spaces with underscores
        $baseUsername = Str::slug($name, '_');
        
        // Check if the username exists
        $username = $baseUsername;
        $counter = 1;
        
        while (User::where('username', $username)->exists()) {
            $username = $baseUsername . '_' . $counter;
            $counter++;
        }
        
        return $username;
    }
}