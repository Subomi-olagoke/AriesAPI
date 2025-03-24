<?php

namespace App\Notifications;

use App\Models\TutoringSession;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SessionCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $session;
    protected $completedBy;

    /**
     * Create a new notification instance.
     */
    public function __construct(TutoringSession $session, User $completedBy)
    {
        $this->session = $session;
        $this->completedBy = $completedBy;
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
        $completedByRole = $this->completedBy->id === $this->session->hireRequest->client_id ? 'Client' : 'Tutor';
        
        return (new MailMessage)
            ->subject('Tutoring Session Completed')
            ->greeting('Hello ' . $notifiable->first_name . ',')
            ->line("Your tutoring session has been marked as completed by the {$completedByRole}.")
            ->line("Topic: " . $this->session->hireRequest->topic)
            ->line("Date: " . $this->session->scheduled_at->format('l, F j, Y'))
            ->line("We hope you had a productive session.")
            ->action('View Session Details', url('/tutoring/sessions/' . $this->session->id))
            ->line('Thank you for using our tutoring services!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Your tutoring session has been marked as completed.',
            'session_id' => $this->session->id,
            'hire_request_id' => $this->session->hire_request_id,
            'completed_by' => [
                'id' => $this->completedBy->id,
                'name' => $this->completedBy->first_name . ' ' . $this->completedBy->last_name,
            ],
            'completed_at' => now()->toIso8601String(),
        ];
    }
}