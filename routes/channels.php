<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('live-class.{id}', function ($user, $id) {
    return Auth::check();
});

Broadcast::channel('live-class.{id}', function ($user, $id) {
    $liveClass = \App\Models\LiveClass::find($id);
    return $liveClass && (
        $user->id === $liveClass->teacher_id || 
        $liveClass->participants()->where('user_id', $user->id)->exists()
    );
});
