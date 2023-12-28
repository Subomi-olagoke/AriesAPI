<?php

/**
 * Registeration and login class
 */

namespace App\Http\Controllers;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Response;

class AuthManager extends Controller {
	function register(Request $request, Response $response) {
		$incomingFields = $request->validate([
			'firstName' => ['required'],
			'LastName' => ['required'],
			'username' => ['required', 'min:3', 'max:20', Rule::unique('users', 'username')],
			'email' => ['required', 'email', Rule::unique('users', 'email')],
			'password' => ['required', 'min:8', 'max:20', 'confirmed'],
			//'role' => ['required', Rule::in(['educator', 'student', 'Admin'])],
		]);
		$incomingFields['password'] = bcrypt($incomingFields['password']);

		/*
			       if ($incomingFields['role'] == 'educator') {
			            return redirect()->route('Educator.reg');
		*/
		// if ($incomingFields['role'] == 'educator') {
		// 	$response['next_step'] = 'educator_registration';
		// }

		// return response()->json($response, 201);

		$user = User::where('username', $username)
			->orWhere('email', $email)
			->first();

		if ($user) {
			return response()->json([
				'message' => 'user already exists']);
		} else {
			$newUser = User::create($incomingFields);
			auth()->login($newUser);
			return response()->json(['message' => 'account created successfully']);
			// return redirect('/');
		}

	}

	//login
	function login(Request $request, Response $response) {
		$credentials = $request->validate([
			'email' => 'required|email',
			'password' => 'required',
		]);

		if (auth()->attempt($credentials)) {
			// Authentication passed
			$user = auth()->user();

			return response()->json([
				'message' => 'Login successful',
				'user' => $user,
				'token' => $user->createToken('authToken')->accessToken,
			], 200);
		}

		// Authentication failed
		return response()->json(['message' => 'Invalid credentials'], 401);

	}

	//logout
	public function destroy(Request $request): RedirectResponse {
		Auth::guard('web')->logout();

		$request->session()->invalidate();

		$request->session()->regenerateToken();

		return redirect('Homefeed');
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

/*
function login(Request $request, Response $response) {
$incomingFields = $request->validate([
'email' => 'required',
'password' => 'required',
]);
if (auth()->attempt(['email' => $incomingFields['email'], 'password' => $incomingFields['password']])) {
return response()->json($response);

}

}
 */