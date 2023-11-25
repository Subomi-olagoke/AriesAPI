<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Profile;


class ProfileController extends Controller
{
    public function showProfile(User $profile) {
        $user = Auth::user();
        $profile = Profile::where('user_id', $user->id)->first();
        return response()->json(['profile' => $profile]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        $profile = Profile::where('user_id', $user->id)->first();

        if (!$profile) {
            $profile = new Profile(['user_id' => $user->id]);
        }

        $profile->fill($request->all());
        $profile->save();

        return response()->json(['profile' => $profile]);
    }

}
