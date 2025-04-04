<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class HireRequestNotification extends BaseNotification
{

    protected $sender;
    protected $message;
    protected $type;

    /**
     * Create a new notification instance.
     * 
     * @param User $sender The user sending/creating the notification
     * @param string|null $message Custom message for the notification
     * @param string $type Type of notification: 'new_request', 'request_accepted', 'request_declined', 'custom'
     */
    public function __construct($sender, $message = null, $type = 'new_request')
    {
        $this->sender = $sender;
        $this->message = $message;
        $this->type = $type;
    }


    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $defaultMessage = '';
        
        switch ($this->type) {
            case 'new_request':
                $defaultMessage = $this->sender->first_name . ' ' . $this->sender->last_name . ' has sent you a hire request.';
                break;
            case 'request_accepted':
                $defaultMessage = $this->sender->first_name . ' ' . $this->sender->last_name . ' has accepted your hire request.';
                break;
            case 'request_declined':
                $defaultMessage = $this->sender->first_name . ' ' . $this->sender->last_name . ' has declined your hire request.';
                break;
            default: // 'custom' or any other type
                $defaultMessage = $this->message ?? 'You have a new notification related to a hire request.';
                break;
        }
        
        // Use custom message if provided, otherwise use the default for this type
        $message = $this->message ?? $defaultMessage;
        
        return [
            'title' => 'Edututor Hire Request',
            'message' => $message,
            'sender_id' => $this->sender->id,
            'sender_name' => $this->sender->first_name . ' ' . $this->sender->last_name,
            'type' => $this->type,
            'notification_type' => 'hire_request',
            'message_content' => $this->message,
        ];
    }
}