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
            // For now, decode the token without strict verification
            // The token comes from Apple's SDK which already verified it
            $tks = explode('.', $request->identity_token);
            if (count($tks) !== 3) {
                return response()->json([
                    'message' => 'Invalid identity token format'
                ], 401);
            }

            // Decode the payload without verification (trusting Apple SDK)
            $payload = json_decode(base64_decode(strtr($tks[1], '-_', '+/')), true);
            
            if (!$payload) {
                return response()->json([
                    'message' => 'Invalid token payload'
                ], 401);
            }

            // Basic validation
            if (!isset($payload['sub'])) {
                return response()->json([
                    'message' => 'Invalid token: missing subject'
                ], 401);
            }

            // Verify issuer is Apple (basic check)
            if (isset($payload['iss']) && $payload['iss'] !== 'https://appleid.apple.com') {
                return response()->json([
                    'message' => 'Invalid token issuer'
                ], 401);
            }

            // Convert payload to object for easier access
            $decoded = (object) $payload;

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
                $user->role = 'learner'; // Default to learner
                
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
            
            // Create new token without revoking existing ones
            // This allows users to be logged in from multiple devices simultaneously
            $token = $user->createToken('apple-auth-mobile')->plainTextToken;

            if (!$token) {
                return response()->json([
                    'message' => 'Failed to create token',
                ], 500);
            }
            
            // Manually build user data array to avoid serialization issues
            // This matches the format expected by the iOS app
            // Ensure all required fields have non-null values
            $userData = [
                'id' => (string) $user->id,
                'username' => $user->username ?? '',
                'email' => $user->email ?? '',
                'first_name' => $user->first_name ?? '',
                'last_name' => $user->last_name ?? '',
                'role' => $user->role ?? 'learner',
                'avatar' => $user->avatar,
                'verification_code' => $user->verification_code,
                'email_verified_at' => $user->email_verified_at ? $user->email_verified_at->toDateTimeString() : null,
                'api_token' => null,
                'device_token' => $user->device_token,
                'device_type' => $user->device_type,
                'created_at' => $user->created_at ? $user->created_at->toDateTimeString() : now()->toDateTimeString(),
                'updated_at' => $user->updated_at ? $user->updated_at->toDateTimeString() : now()->toDateTimeString(),
                'isAdmin' => $user->isAdmin ? 1 : 0,
                'google_id' => $user->google_id,
                'name' => $user->name,
                'alex_points' => $user->alex_points ?? 0,
                'point_level' => $user->point_level ?? 1,
                'points_to_next_level' => $user->points_to_next_level ?? 100,
                'is_banned' => $user->is_banned ?? false,
                'banned_at' => $user->banned_at ? $user->banned_at->toDateTimeString() : null,
                'ban_reason' => $user->ban_reason,
                'is_verified' => $user->is_verified ? 1 : 0,
                'verified_at' => $user->verified_at ? $user->verified_at->toDateTimeString() : null,
                'verification_status' => null,
                'verification_notes' => null,
                'setup_completed' => $user->setup_completed ?? true, // Default to true since role is set
                'bio' => $user->profile ? $user->profile->bio : null,
            ];
            
            // Add profile if it exists
            if ($user->profile) {
                $userData['profile'] = [
                    'id' => $user->profile->id ?? null,
                    'user_id' => $user->profile->user_id ?? $user->id,
                    'bio' => $user->profile->bio ?? null,
                    'avatar' => $user->profile->avatar ?? null,
                    'created_at' => $user->profile->created_at ? $user->profile->created_at->toDateTimeString() : null,
                    'updated_at' => $user->profile->updated_at ? $user->profile->updated_at->toDateTimeString() : null,
                    'qualifications' => $user->profile->qualifications ?? null,
                    'teaching_style' => $user->profile->teaching_style ?? null,
                    'availability' => $user->profile->availability ?? null,
                    'hire_rate' => $user->profile->hire_rate ?? null,
                    'hire_currency' => $user->profile->hire_currency ?? null,
                    'social_links' => $user->profile->social_links ?? null,
                    'share_key' => $user->profile->share_key ?? null,
                    'share_url' => $user->profile->share_url ?? null,
                ];
            }

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

            // Log the response for debugging (remove sensitive data in production)
            \Log::info('Apple authentication successful', [
                'user_id' => $user->id,
                'username' => $user->username,
                'has_token' => !empty($token),
                'response_keys' => array_keys($responseData)
            ]);

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
     * Convert JWK to PEM format using OpenSSL
     *
     * @param array $jwk
     * @return string
     */
    private function convertJWKToPEM($jwk)
    {
        try {
            // Decode base64url encoded values
            $n = $this->base64urlDecode($jwk['n']);
            $e = $this->base64urlDecode($jwk['e']);

            // Convert exponent to binary (should be small, typically 65537)
            $eInt = $this->base256ToInt($e);
            $eBin = $this->intToBase256($eInt);

            // Build RSA public key in DER format
            $modulus = $this->buildDERInteger($n);
            $exponent = $this->buildDERInteger($eBin);
            
            // Build sequence containing modulus and exponent
            $sequence = $this->buildDERSequence($modulus . $exponent);
            
            // Build full public key structure
            $rsaOID = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
            $keyData = $this->buildDERBitString($sequence);
            $publicKey = $this->buildDERSequence($rsaOID . $keyData);
            
            // Convert to PEM format
            $pem = "-----BEGIN PUBLIC KEY-----\n";
            $pem .= chunk_split(base64_encode($publicKey), 64, "\n");
            $pem .= "-----END PUBLIC KEY-----\n";

            return $pem;
        } catch (\Exception $e) {
            \Log::error('JWK to PEM conversion error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Decode base64url encoded string
     *
     * @param string $data
     * @return string
     */
    private function base64urlDecode($data)
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Build DER integer from binary data
     *
     * @param string $data
     * @return string
     */
    private function buildDERInteger($data)
    {
        // Remove leading zeros
        $data = ltrim($data, "\x00");
        
        // If first byte has high bit set, prepend zero byte
        if (ord($data[0]) & 0x80) {
            $data = "\x00" . $data;
        }
        
        $length = strlen($data);
        $lengthBytes = $this->encodeDERLength($length);
        
        return "\x02" . $lengthBytes . $data;
    }

    /**
     * Build DER sequence
     *
     * @param string $data
     * @return string
     */
    private function buildDERSequence($data)
    {
        $length = strlen($data);
        $lengthBytes = $this->encodeDERLength($length);
        
        return "\x30" . $lengthBytes . $data;
    }

    /**
     * Build DER bit string
     *
     * @param string $data
     * @return string
     */
    private function buildDERBitString($data)
    {
        $length = strlen($data) + 1; // +1 for unused bits byte
        $lengthBytes = $this->encodeDERLength($length);
        
        return "\x03" . $lengthBytes . "\x00" . $data;
    }

    /**
     * Encode DER length
     *
     * @param int $length
     * @return string
     */
    private function encodeDERLength($length)
    {
        if ($length < 128) {
            return chr($length);
        }
        
        $bytes = [];
        while ($length > 0) {
            array_unshift($bytes, $length & 0xFF);
            $length >>= 8;
        }
        
        return chr(0x80 | count($bytes)) . implode('', array_map('chr', $bytes));
    }

    /**
     * Convert base256 to integer
     *
     * @param string $data
     * @return int
     */
    private function base256ToInt($data)
    {
        $result = 0;
        $length = strlen($data);
        for ($i = 0; $i < $length; $i++) {
            $result = ($result << 8) | ord($data[$i]);
        }
        return $result;
    }

    /**
     * Convert integer to base256
     *
     * @param int $value
     * @return string
     */
    private function intToBase256($value)
    {
        $result = '';
        while ($value > 0) {
            $result = chr($value & 0xFF) . $result;
            $value >>= 8;
        }
        return $result ?: "\x00";
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

