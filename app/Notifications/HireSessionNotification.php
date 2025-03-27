<?php

namespace App\Notifications;

use App\Models\HireRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HireRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $sender;
    protected $message;
    protected $hireRequest;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $sender, $messageOrHireRequest = null)
    {
        $this->sender = $sender;
        
        if ($messageOrHireRequest instanceof HireRequest) {
            $this->hireRequest = $messageOrHireRequest;
            $this->message = "New hire request for: {$this->hireRequest->topic}";
        } else {
            $this->message = $messageOrHireRequest;
        }
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = new MailMessage;
        $message->subject('Educational Platform - New Hire Request');
        
        if ($this->hireRequest) {
            $message->line("You have received a new hire request from {$this->sender->first_name} {$this->sender->last_name}.")
                   ->line("Topic: {$this->hireRequest->topic}")
                   ->line("Duration: {$this->hireRequest->duration}")
                   ->action('View Request', url('/tutor/requests'));
        } else {
            $message->line($this->message);
        }
        
        return $message->line('Thank you for using our platform!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        $data = [
            'message' => $this->message,
            'sender_id' => $this->sender->id,
            'sender_name' => $this->sender->first_name . ' ' . $this->sender->last_name,
            'sender_avatar' => $this->sender->avatar,
        ];
        
        if ($this->hireRequest) {
            $data['hire_request_id'] = $this->hireRequest->id;
            $data['topic'] = $this->hireRequest->topic;
        }
        
        return $data;
    }
}