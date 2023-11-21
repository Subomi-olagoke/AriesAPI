<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MailController extends Controller
{
  /*  public function index() {
        $data = [
            'subject'=>'Password Reset',
            'body' => 'Click the link to reset your password'
        ];
        $user = User::where('email', $request->input('email'))->first();
        try {
            Mail::to($user->email)->send(new PasswordResetMail($data));
            return response('Email sent successfully');
            //catch
        } catch (Exception $th) {
            return response('Err sending mail', 500);
        }
    }*/
    //
}
