<?php

namespace App\Http\Controllers;

use App\Mail\MailResetPasswordRequest;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

    class AuthManager extends Controller {
    public function register(Request $request) {
        $incomingFields = $request->validate([
            'first_name' => ['required'],
            'last_name' => ['required'],
            'username' => ['required', 'min:3', 'max:20', Rule::unique('users', 'username')],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => ['required','min:8','max:20',Password::min(8)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->uncompromised(),
            'confirmed'
            ],
        ]);

        $incomingFields['password'] = bcrypt($incomingFields['password']);

        $user = new User();
        $user->username = $request->username;
        $user->first_name = $request->first_name;
        $user->last_name = $request->last_name;
        $user->email = $request->email;
        $user->password = $incomingFields['password'];


        $save = $user->save();

        if ($save) {
            Auth::login($user);

            $token = $user->createToken('auth_token')->plainTextToken;

            // Include profile data if user role is provided and is educator
            $profileData = null;
            if ($request->has('role') && $request->role === 'educator') {
                // Profile won't exist yet for new users, but we include the field for consistency
                $profileData = null;
            }

            return response()->json([
                'message' => 'Registration successful',
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
                'educator_profile' => $profileData
            ], 201);
        } else {
            return response()->json([
                'message' => 'Some error occurred, please try again',
            ], 500);
        }

    }

    public function login(Request $request) {
        $request->validate([
            'login' => 'required|string', // login can be either username or email
            'password' => 'required',
        ]);

        // Determine if the input is an email or username
        $loginField = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        
        // Create credentials array with the correct field name
        $credentials = [
            $loginField => $request->login,
            'password' => $request->password
        ];

        if (!Auth::attempt($credentials)) {
            // Authentication failed
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        $user = Auth::user();

        // Revoke all existing tokens for the user
        $user->tokens()->delete();

        // Create a new token
        $token = $user->createToken('authToken')->plainTextToken;

        if (!$token) {
            // Token creation failed
            return response()->json([
                'message' => 'Failed to create token',
            ], 500);
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

        return response()->json($responseData, 200)->cookie(
            'access_token', $token, 1440, null, null, false, true // 1440 minutes = 24 hours
        );
    }

    // public function logoutTest(Request $request) {
    //     Auth::guard('sanctum')->forgetUser();
    //     return response()->json([
    //         'message' => 'Logged out successfully',
    //     ], 200);

    // }

    public function logout(Request $request) {
        $request->session()->invalidate();  //Invalidate session
        $request->session()->regenerateToken();

        $user = $request->user();
        if (!$user) {
            return response()->json(
                ['error' => 'Unauthorized'], 401);
        }

          $token = $user ? $user->currentAccessToken() : null;
            if($token) {
                $token->delete();
                return response()->json([
                    'message' => 'Logged out successfully',
                    ], 200);
            }
            return response()->json(['error' => 'Token not found'], 404);
    }


    public function forgorPassword(Request $request) {
        $request->validate([
            'email' => 'required|string|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'The provided email is not registered in our system.',
            ], 404);
        }

        $code = rand(111111, 999999);
        $user->verification_code = $code;

        if ($user->save()) {
            $emailData = array(
                'heading' => 'Reset Password Request',
                'username' => $user->username,
                'email' => $user->email,
                'code' => $user->verification_code,
            );

            Mail::to($emailData['email'])->queue(new MailResetPasswordRequest($emailData));

            return response()->json([
                'message' => 'We have sent a verification code to your email',
            ], 200);
        } else {
            return response()->json([
                'message' => 'Sorry, an error occurred, please try again',
            ], 500);
        }
    }

    public function resetPassword(Request $request) {
        $request->validate([
            'email' => 'required|string|email',
            'verification_code' => 'required|integer',
            'new_password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
        ]);

        $user = User::where('email', $request->email)->where('verification_code', $request->verification_code)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found/invalid code',
            ], 404);
        }

        $user->password = bcrypt($request->new_password);
        $user->verification_code = null;

        if ($user->save()) {
            return response()->json([
                'message' => 'Password updated successfully',
            ], 200);
        } else {
            return response()->json([
                'message' => 'Some error occurred, please try again later',
            ], 500);
        }
    }

    public function fetchUser($id) {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json($user, 200);
    }

    public function __invoke(EmailVerificationRequest $request): RedirectResponse {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(RouteServiceProvider::HOME . '?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->intended(RouteServiceProvider::HOME . '?verified=1');
    }
}
