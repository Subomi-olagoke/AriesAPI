<?php
namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

  use Illuminate\Support\Facades\Hash;
  use Illuminate\Support\Facades\DB;
  use Illuminate\Http\Request;
  use App\Models\User;


class ForgotPasswordManager extends Controller
{

    function forgotPassword(Request $request) {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $token = Str::random(64);

        // Store the generated token in a temporary database table
        DB::table('password_reset_temporary_tokens')->insert([
            'email' => $request->email,
            'token' => $token,
            'created_at' => Carbon::now(),
        ]);

        // Send email with the reset link containing the token
        $resetLink = route('reset.password', ['email' => $request->email, 'token' => $token]);

        return response()->json(['reset_link' => $resetLink, 'message' => 'Password reset link sent.']);
    }




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

}






















































