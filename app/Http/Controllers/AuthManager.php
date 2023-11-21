<?php

/**
 * Registeration and login class
 */

namespace App\Http\Controllers;

use Response;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

class AuthManager extends Controller
{
    function register(Request $request, Response $response) {
        $incomingFields = $request->validate([
            'firstName' => ['required',],
            'LastName' => ['required'],
            'username' => ['required', 'min:3', 'max:20', Rule::unique('users', 'username')],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => ['required', 'min:8', 'max:20', 'confirmed'],
            'role' => ['required', Rule::in(['educator', 'student', 'Admin'])]
        ]);
       /*
       if ($incomingFields['role'] == 'educator') {
            return redirect()->route('Educator.reg');
        }*/
        $incomingFields['password'] = bcrypt($incomingFields['password']);

        $user = User::create($incomingFields);
        auth()->login($user);
        return response()->json($response);
       // return redirect('/');
    }


    //login
    function login(Request $request, Response $response) {
        $incomingFields = $request->validate([
            'email' => 'required',
            'password' => 'required'
        ]);
        if(auth()->attempt(['email' => $incomingFields['email'],'password' => $incomingFields['password'] ])) {
            return response()->json($response);

        }

    }


    //logout
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('Homefeed');
    }


    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(RouteServiceProvider::HOME . '?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->intended(RouteServiceProvider::HOME . '?verified=1');
    }

}
