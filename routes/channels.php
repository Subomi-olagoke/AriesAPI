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

// New channel for private messaging
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return $user->id === $userId;
});

// Channel for collaboration channels
Broadcast::channel('channel.{channelId}', function ($user, $channelId) {
    $channel = \App\Models\Channel::find($channelId);
    if (!$channel) {
        return false;
    }
    return $channel->isMember($user);
});

// Document collaboration channels
Broadcast::channel('document.{documentId}', function ($user, $documentId) {
    // Get document content
    $content = \App\Models\CollaborativeContent::with('space.channel')->find($documentId);
    if (!$content) {
        return false;
    }
    
    // Check if user is a member of the channel
    $channel = $content->space->channel;
    if (!$channel->isMember($user)) {
        return false;
    }
    
    // At minimum, user should be able to view the document
    return $content->canView($user);
});