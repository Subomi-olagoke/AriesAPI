<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Response;

class AuthManager extends Controller
{
    function register(Request $request, Response $response) {
        $incomingFields = $request->validate([
            'username' => ['required', 'min:3', 'max:20', Rule::unique('users', 'username')],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => ['required', 'min:8', 'max:20', 'confirmed']
        ]);
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
           // $request->session()->regenerate();
            //return redirect('/');

        } /*else {
            return redirect('/');
        }*/

    }

   /* public function showCorrectHomePage() {
        if (auth()->check()) {
            return route('homepage-feed');
        } else {
            return route('homepage');
        }

    }*/
}
