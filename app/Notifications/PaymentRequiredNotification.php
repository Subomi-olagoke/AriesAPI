<?php

namespace App\Notifications;

use App\Models\TutoringSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentRequiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $session;

    /**
     * Create a new notification instance.
     */
    public function __construct(TutoringSession $session)
    {
        $this->session = $session;
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
        $amount = $this->session->hireRequest->rate_per_session;
        $currency = $this->session->hireRequest->currency;
        
        return (new MailMessage)
            ->subject('Payment Required for Tutoring Session')
            ->greeting('Hello ' . $notifiable->first_name . ',')
            ->line("Your tutoring session has been scheduled successfully. Payment is required to confirm the booking.")
            ->line("Session Topic: " . $this->session->hireRequest->topic)
            ->line("Date and Time: " . $this->session->scheduled_at->format('l, F j, Y \a\t g:i A'))
            ->line("Amount: {$currency} " . number_format($amount, 2))
            ->action('Make Payment', url('/tutoring/sessions/' . $this->session->id . '/payment'))
            ->line('Your session will be confirmed after payment is received.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Payment required for scheduled tutoring session.',
            'session_id' => $this->session->id,
            'hire_request_id' => $this->session->hire_request_id,
            'amount' => $this->session->hireRequest->rate_per_session,
            'currency' => $this->session->hireRequest->currency,
            'scheduled_at' => $this->session->scheduled_at->toIso8601String(),
        ];
    }
}