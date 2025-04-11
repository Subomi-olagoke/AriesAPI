<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class HireRequestNotification extends BaseNotification // Assuming it extends BaseNotification
{
    use Queueable;

    protected $sender;
    protected $message;
    protected $type;

    /**
     * Create a new notification instance.
     *
     * @param User $sender
     * @param string $message
     * @param string $type
     * @return void
     */
    public function __construct(User $sender, $message, $type = 'new_request')
    {
        $this->sender = $sender;
        $this->message = $message;
        $this->type = $type;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification.
     *
     * IMPORTANT: Use the same signature as the parent class
     * 
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'sender_id' => $this->sender->id,
            'sender_name' => $this->sender->first_name . ' ' . $this->sender->last_name,
            'sender_avatar' => $this->sender->avatar,
            'message' => $this->message,
            'type' => $this->type
        ];
    }
}