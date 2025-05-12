<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class EducatorAuthController extends Controller
{
    /**
     * Show the educator login form
     */
    public function showLoginForm()
    {
        return view('educators.login');
    }

    /**
     * Handle educator login
     */
    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        // Determine if input is email or username
        $loginField = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        
        $credentials = [
            $loginField => $request->login,
            'password' => $request->password,
        ];

        // Add educator role check to credentials
        $credentials['role'] = User::ROLE_EDUCATOR;

        // Attempt login
        if (Auth::attempt($credentials, $request->filled('remember'))) {
            $request->session()->regenerate();
            
            // Always redirect to dashboard after login
            return redirect()->route('educator.dashboard');
        }

        throw ValidationException::withMessages([
            'login' => ['The provided credentials do not match our records or you do not have educator privileges.'],
        ]);
    }

    /**
     * Log the educator out
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('educator.login');
    }
}