<?php

namespace App\Http\Controllers;

use App\Models\Educators;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use App\Http\Requests\StoreEducatorsRequest;
use App\Http\Requests\UpdateEducatorsRequest;

class EducatorsController extends Controller
{
    public function register(Request $request, Response $response) {
     $incomingFields = $request->validate([
        'firstName' => ['required'],
        'LastName' => ['required'],
        'username' => ['required', 'min:3', 'max:20', Rule::unique('users', 'username')],
        'password' => ['required', 'min:8', 'max:20', 'confirmed'],
        'field' => ['required', Rule::in(['Business', 'Advertising', 'Copy-writing', 'Story Writing', 'Mathematics', 'Computer Science', 'Programming', 'Architecture', 'Engineering', 'others'])]

     ]);

     $incomingFields['password'] = bcrypt($incomingFields['password']);

     $Educators = Educators::create($incomingFields);
     auth()->login($Educators);
     return response()->json($response);
    // return redirect('/');

    }

    function login(Request $request, Response $response) {
        $incomingFields = $request->validate([
            'email' => 'required',
            'password' => 'required'
        ]);
        if(auth()->attempt(['email' => $incomingFields['email'],'password' => $incomingFields['password'] ])) {
            return response()->json($response);
        }

    }
}
