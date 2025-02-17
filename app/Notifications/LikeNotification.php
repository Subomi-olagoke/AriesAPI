<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LikeNotification extends Notification
{
    use Queueable;

    protected $post;
    protected $comment;
    protected $course;
    protected $user;

    /**
     * Create a new notification instance.
     */
    public function __construct($post, $user, $comment, $course)
    {
        $this->post = $post;
        $this->user = $user;
        $this->comment = $comment;
        $this->course = $course;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase() {
        \Log::info('Notification Triggered!');

        $post_id = $this->post?->id;
        $comment_id = $this->comment?->id;
        $course_id = $this->course?->id;

        if($post_id) {
            return [
                'message' => "{$this->user->username} liked your post.",
                'avatar' => $this->user->avatar,
                'post_id' => $post_id,
                'liked_by' => $this->user->id,
                'liked_by_username' => $this->user->username,
            ];
        }
        if($comment_id) {
            return [
                'message' => "{$this->user->username} liked your comment.",
                'avatar' => $this->user->avatar,
                'comment_id' => $comment_id,
                'liked_by' => $this->user->id,
                'liked_by_username' => $this->user->username,
            ];
        }
        if($course_id) {
            return [
                'message' => "{$this->user->username} liked your course.",
                'avatar' => $this->user->avatar,
                'course_id' => $course_id,
                'liked_by' => $this->user->id,
                'liked_by_username' => $this->user->username,
            ];

           // \Log::info('Notification Data:', $data);

            //return $data;
        }
     }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->line('The introduction to the notification.')
                    ->action('Notification Action', url('/'))
                    ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
