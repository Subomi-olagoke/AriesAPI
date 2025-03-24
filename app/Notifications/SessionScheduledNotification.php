<?php

namespace App\Notifications;

use App\Models\TutoringSession;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SessionScheduledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $session;
    protected $otherUser;

    /**
     * Create a new notification instance.
     */
    public function __construct(TutoringSession $session, User $otherUser)
    {
        $this->session = $session;
        $this->otherUser = $otherUser;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $userRole = $this->session->hireRequest->client_id === $notifiable->id ? 'Client' : 'Tutor';
        $otherRole = $userRole === 'Client' ? 'Tutor' : 'Client';
        
        return (new MailMessage)
            ->subject('Tutoring Session Scheduled')
            ->greeting('Hello ' . $notifiable->first_name . ',')
            ->line("Your tutoring session has been scheduled.")
            ->line("Topic: " . $this->session->hireRequest->topic)
            ->line("Date and Time: " . $this->session->scheduled_at->format('l, F j, Y \a\t g:i A'))
            ->line("Duration: " . $this->session->duration_minutes . " minutes")
            ->line("{$otherRole}: " . $this->otherUser->first_name . ' ' . $this->otherUser->last_name)
            ->action('Join Meeting', $this->session->google_meet_link)
            ->line('Please make sure to join the meeting on time.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'A tutoring session has been scheduled.',
            'session_id' => $this->session->id,
            'hire_request_id' => $this->session->hire_request_id,
            'google_meet_link' => $this->session->google_meet_link,
            'scheduled_at' => $this->session->scheduled_at->toIso8601String(),
            'other_user' => [
                'id' => $this->otherUser->id,
                'name' => $this->otherUser->first_name . ' ' . $this->otherUser->last_name,
            ]
        ];
    }
}