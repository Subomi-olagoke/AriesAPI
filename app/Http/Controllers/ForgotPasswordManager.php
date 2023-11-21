<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;

class ForgotPasswordManager extends Controller
{

    function forgotPassword(Request $request) {
        $request->validate([
            'email' => 'required|email|exists:users',
        ]);

        $token = Str::random(64);

        DB::table('password_reset_tokens')->insert([
            'email' => $request->email,
            'token' => $token,
            'created_at' => Carbon::now()
        ]);

        Mail::send('email', ['token'=>$token], function ($message) use ($request) {
            $message->to($request->email);
            $message->subject("Reset Password");
        });
        return redirect()->to(route('forgot.password'))
        ->with('succes', "We have sent you an email to reset your password.");



    }
    function resetPassword($token) {
        return redirect()->route('reset-password', compact('token'));

    }

    public function resetPasswordPost(Request $request) {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
            'password_confirmation' => 'required'
        ]);

        $updatePassword = DB::table('password_resets')
        ->where([
            'email' => $request->email,
            'token' => $request->token
        ])->first();

        if(!$updatePassword) {
            return redirect()->to(route('reset.password'))->with('error', 'invalid');
        }

        User::where('email', $request->email)->update(['password'=> Hash::make($request->password)]);

        DB::table('password_reset_tokens')->where(['email'=> $request->email])->delete();

        return redirect()->to(route('login'))->with('success', 'Your password has been reset');

      }  /*$status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function(User $user, string $password) {
                $user->forcefill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }


        );

        return $status === Password::PASSWORD_RESET
        ? redirect()->route('login')->with('status', __($status))
        : back()->withErrors(['email' => [__($status)]])->middleware('guest')->name('password.update');

    } */
}
