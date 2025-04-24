<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class GeneralNotification extends BaseNotification implements ShouldQueue
{
    use Queueable;

    protected $message;
    protected $type;
    protected $status;
    protected $data;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $message, string $type, string $status, array $data = [])
    {
        $this->message = $message;
        $this->type = $type;
        $this->status = $status;
        $this->data = $data;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'message' => $this->message,
            'type' => $this->type,
            'status' => $this->status,
            'data' => $this->data,
        ];
    }
}