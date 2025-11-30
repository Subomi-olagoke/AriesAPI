<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use GuzzleHttp\Client;

class AppleController extends Controller
{
    /**
     * Authenticate a user with an Apple identity token from mobile app.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function authenticateWithApple(Request $request)
    {
        $request->validate([
            'identity_token' => 'required|string',
        ]);

        try {
            // Decode the JWT token without verification first to get the header
            $tks = explode('.', $request->identity_token);
            if (count($tks) !== 3) {
                return response()->json([
                    'message' => 'Invalid identity token format'
                ], 401);
            }

            $header = json_decode(base64_decode(strtr($tks[0], '-_', '+/')), true);
            if (!$header || !isset($header['kid'])) {
                return response()->json([
                    'message' => 'Invalid token header'
                ], 401);
            }

            // Get Apple's public keys
            $publicKeys = $this->getApplePublicKeys();
            if (!$publicKeys || !isset($publicKeys[$header['kid']])) {
                return response()->json([
                    'message' => 'Unable to verify token with Apple'
                ], 401);
            }

            // Verify and decode the token
            $publicKey = $publicKeys[$header['kid']];
            $decoded = JWT::decode($request->identity_token, new Key($publicKey, 'RS256'));

            // Verify the token is for our app
            $clientId = config('services.apple.client_id');
            if (isset($decoded->aud) && $decoded->aud !== $clientId) {
                return response()->json([
                    'message' => 'Token audience mismatch'
                ], 401);
            }

            // Verify issuer is Apple
            if (isset($decoded->iss) && $decoded->iss !== 'https://appleid.apple.com') {
                return response()->json([
                    'message' => 'Invalid token issuer'
                ], 401);
            }

            // Get user data from token
            $appleId = $decoded->sub;
            $email = $decoded->email ?? null;
            $emailVerified = isset($decoded->email_verified) ? (bool)$decoded->email_verified : false;
            
            // Apple may not always provide email in subsequent logins
            // If email is not in token, try to find user by apple_id
            $user = null;
            
            if ($email) {
                $user = User::where('email', $email)->first();
            }
            
            // If not found by email, try by apple_id
            if (!$user && $appleId) {
                $user = User::where('apple_id', $appleId)->first();
            }

            // Create new user if doesn't exist
            if (!$user) {
                $user = new User();
                
                // Generate username from email or a default
                if ($email) {
                    $name = explode('@', $email)[0];
                } else {
                    $name = 'apple_user_' . substr($appleId, 0, 8);
                }
                
                $user->username = $this->generateUsername($name);
                $user->first_name = $name;
                $user->last_name = '';
                $user->email = $email ?? $this->generateTemporaryEmail($appleId);
                $user->password = Hash::make(Str::random(16));
                
                if ($emailVerified) {
                    $user->email_verified_at = now();
                }
                
                $user->apple_id = $appleId;
                $user->save();
            } else {
                // Update apple_id if not set
                if (!$user->apple_id) {
                    $user->apple_id = $appleId;
                    $user->save();
                }
                
                // Update email verification if email is verified in token
                if ($emailVerified && !$user->email_verified_at) {
                    $user->email_verified_at = now();
                    $user->save();
                }
            }

            // Load the user's profile to include bio and other profile data
            $user->load('profile');
            
            // Create a response array that includes profile information
            $userData = $user->toArray();
            
            // If profile exists, add bio to user object directly
            if ($user->profile) {
                $userData['bio'] = $user->profile->bio;
            } else {
                $userData['bio'] = null;
            }

            // Create new token without revoking existing ones
            // This allows users to be logged in from multiple devices simultaneously
            $token = $user->createToken('apple-auth-mobile')->plainTextToken;

            $responseData = [
                'user' => $userData,
                'token' => $token,
                'token_type' => 'Bearer',
            ];

            // Only add educator-specific fields if user is an educator
            if ($user->role === User::ROLE_EDUCATOR && $user->profile) {
                $responseData['qualifications'] = $user->profile->qualifications;
                $responseData['teaching_style'] = $user->profile->teaching_style;
            }

            return response()->json($responseData);
            
        } catch (\Exception $e) {
            \Log::error('Apple authentication error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Apple authentication failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Apple's public keys for JWT verification
     *
     * @return array|null
     */
    private function getApplePublicKeys()
    {
        try {
            $client = new Client();
            $response = $client->get('https://appleid.apple.com/auth/keys');
            $keys = json_decode($response->getBody()->getContents(), true);

            if (!isset($keys['keys'])) {
                return null;
            }

            $publicKeys = [];
            foreach ($keys['keys'] as $key) {
                $publicKeys[$key['kid']] = $this->convertJWKToPEM($key);
            }

            return $publicKeys;
        } catch (\Exception $e) {
            \Log::error('Failed to fetch Apple public keys: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Convert JWK to PEM format
     *
     * @param array $jwk
     * @return string
     */
    private function convertJWKToPEM($jwk)
    {
        $n = base64_decode(strtr($jwk['n'], '-_', '+/'));
        $e = base64_decode(strtr($jwk['e'], '-_', '+/'));

        $modulus = $this->bin2bigint($n);
        $exponent = $this->bin2bigint($e);

        $components = [
            'modulus' => $modulus,
            'exponent' => $exponent,
        ];

        $number = pack('Ca*a*', 0x30, $this->encodeLength(strlen($components['modulus']) + strlen($components['exponent'])), $components['modulus'], $components['exponent']);
        $number = pack('Ca*a*', 0x30, $this->encodeLength(strlen($number)), $number);
        $number = pack('Ca*', 0x00, $number);

        $rsaOID = pack('H*', '300d06092a864886f70d0101010500');
        $number = chr(0x00) . chr(0x00) . $number;
        $number = pack('Ca*a*', 0x30, $this->encodeLength(strlen($rsaOID . $number)), $rsaOID . $number);

        $pem = "-----BEGIN PUBLIC KEY-----\n";
        $pem .= chunk_split(base64_encode($number), 64, "\n");
        $pem .= "-----END PUBLIC KEY-----\n";

        return $pem;
    }

    /**
     * Convert binary to big integer
     *
     * @param string $bin
     * @return string
     */
    private function bin2bigint($bin)
    {
        $hex = bin2hex($bin);
        $bigint = '';
        for ($i = 0; $i < strlen($hex); $i += 2) {
            $bigint .= chr(hexdec(substr($hex, $i, 2)));
        }
        return $bigint;
    }

    /**
     * Encode length for DER encoding
     *
     * @param int $length
     * @return string
     */
    private function encodeLength($length)
    {
        if ($length < 0x80) {
            return chr($length);
        }
        $temp = ltrim(pack('N', $length), chr(0));
        return pack('Ca*', 0x80 | strlen($temp), $temp);
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

    /**
     * Generate a temporary email for users who don't provide email
     *
     * @param string $appleId
     * @return string
     */
    private function generateTemporaryEmail(string $appleId): string
    {
        // Generate a unique temporary email
        $baseEmail = 'apple_' . substr($appleId, 0, 8) . '@temp.aries.app';
        $email = $baseEmail;
        $counter = 1;
        
        while (User::where('email', $email)->exists()) {
            $email = 'apple_' . substr($appleId, 0, 8) . '_' . $counter . '@temp.aries.app';
            $counter++;
        }
        
        return $email;
    }
}

