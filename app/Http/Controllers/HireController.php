<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\HireInstructorNotification;
use Illuminate\Http\Request;
use App\Models\HireInstructor;

class HireController extends Controller
{
    public function hireInstructor(Request $request, $id) {
        $user = $request->user();
        $existCheck = HireInstructor::where([['user_id', '=', $user->id],
            ['hireduser', '=', $user->id]])->count();
            if($existCheck) {
                return response()->json([
                    'message' => 'already hired'
                ], 409);
            }
            $newHire = new HireInstructor();
            $newHire->user_id = $user->id;
            $newHire->hireduser = $id;
            $save = $newHire->save();

            if($save) {
                $notifiable = User::find($newHire->hireduser);
                $notifiable->notify(new HireInstructorNotification(auth()->user()));

                return response()->json([
                    'message' => 'hire request sent'
                ], 201);
            }
    }
}
