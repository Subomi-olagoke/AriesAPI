<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class CommentNotification extends BaseNotification
{

    protected $comment;
    protected $user;

    /**
     * Create a new notification instance.
     */
    public function __construct($comment, $user)
    {
        $this->comment = $comment;
        $this->user = $user;
    }


    public function toDatabase($notifiable)
    {
        // Include the comment content, but truncate if it's too long
        $commentContent = $this->comment->content;
        $truncatedContent = strlen($commentContent) > 100 
            ? substr($commentContent, 0, 97) . '...' 
            : $commentContent;
        
        $username = $this->user->username ?? $this->user->first_name ?? 'Someone';
            
        return [
            'message' => $commentContent,
            'sender_name' => $username,
            'comment_id' => $this->comment->id,
            'avatar' => $this->user->avatar,
            'post_id' => $this->comment->post_id,
            'commented_by' => $this->user->id,
            'comment_content' => $this->comment->content, // Full comment content for display
        ];
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
        // Include the comment content, but truncate if it's too long
        $commentContent = $this->comment->content;
        $truncatedContent = strlen($commentContent) > 100 
            ? substr($commentContent, 0, 97) . '...' 
            : $commentContent;
            
        $username = $this->user->username ?? $this->user->first_name ?? 'Someone';
            
        return [
            'title' => 'New Comment',
            'message' => $commentContent,
            'sender_name' => $username,
            'comment_id' => $this->comment->id,
            'avatar' => $this->user->avatar,
            'post_id' => $this->comment->post_id,
            'commented_by' => $this->user->id,
            'comment_content' => $this->comment->content,
            'notification_type' => 'comment',
        ];
    }
}