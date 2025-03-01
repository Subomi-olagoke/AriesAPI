<?php

namespace App\Notifications;

use App\Models\CourseEnrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CourseEnrollmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $enrollment;

    /**
     * Create a new notification instance.
     */
    public function __construct(CourseEnrollment $enrollment)
    {
        $this->enrollment = $enrollment;
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
        $user = $this->enrollment->user;
        $course = $this->enrollment->course;
        
        return (new MailMessage)
            ->subject('New Enrollment in Your Course')
            ->greeting('Hello ' . $notifiable->first_name . ',')
            ->line($user->first_name . ' ' . $user->last_name . ' has enrolled in your course.')
            ->line('Course: ' . $course->title)
            ->action('View Course', url('/courses/' . $course->id))
            ->line('Thank you for creating great educational content!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        $user = $this->enrollment->user;
        $course = $this->enrollment->course;
        
        return [
            'message' => $user->first_name . ' ' . $user->last_name . ' enrolled in your course ' . $course->title,
            'enrollment_id' => $this->enrollment->id,
            'course_id' => $course->id,
            'user_id' => $user->id,
            'user_name' => $user->first_name . ' ' . $user->last_name,
            'course_title' => $course->title
        ];
    }
}