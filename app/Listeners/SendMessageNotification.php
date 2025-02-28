<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Notifications\NewMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendMessageNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(MessageSent $event): void
    {
        $message = $event->message;
        $conversation = $message->conversation;
        
        // Determine the recipient
        $recipientId = ($conversation->user_one_id == $message->sender_id) 
            ? $conversation->user_two_id 
            : $conversation->user_one_id;
            
        $recipient = \App\Models\User::find($recipientId);
        
        if ($recipient) {
            $recipient->notify(new NewMessage($message));
        }
    }
}