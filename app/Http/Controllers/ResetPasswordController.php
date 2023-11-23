<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Support\Str;

class ResetPasswordController extends Controller
{
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
            'password' => 'required|string|confirmed|min:8',
        ], [
            'email.exists' => 'The provided email does not exist.',
    'token.required' => 'The reset token is required.',
        ]
    );

        $tokenData = DB::table('password_reset_temporary_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$tokenData || Carbon::parse($tokenData->created_at)->addMinutes(60)->isPast()) {
            return response()->json(['error' => 'Invalid token'], 400);
        }

        $user = User::where('email', $request->email)->first();

          // Check if the user exists
    if (!$user) {
        return response()->json(['error' => 'The provided email does not exist.'], 404);
    }


        $user->update(['password' => Hash::make($request->password)]);

        DB::table('password_reset_temporary_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->delete();

        return response()->json(['message' => 'Password reset successfully']);
    }

    // Additional method to generate a reset token
    public function generateResetToken(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $token = Str::random(64);

        DB::table('password_reset_temporary_tokens')->updateOrInsert(
            ['email' => $request->email],
            ['token' => $token, 'created_at' => Carbon::now()]
        );
        $resetLink = route('reset.password.form', ['token' => $token]);


        return response()->json(['token' => $token, 'message' => 'Token generated successfully']);
    }
}
