<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CommentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $commenter;
    protected $comment;
    protected $commentableType;
    protected $commentableId;

    /**
     * Create a new notification instance.
     */
    public function __construct($commenter, $comment, $commentableType, $commentableId)
    {
        $this->commenter = $commenter;
        $this->comment = $comment;
        $this->commentableType = $commentableType;
        $this->commentableId = $commentableId;
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

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $contentType = $this->getContentTypeName();
        $commentPreview = strlen($this->comment->comment) > 100 
            ? substr($this->comment->comment, 0, 100) . '...' 
            : $this->comment->comment;

        return [
            'message' => "{$this->commenter->username} commented on your {$contentType}.",
            'sender_name' => "{$this->commenter->first_name} {$this->commenter->last_name}",
            'avatar' => $this->commenter->avatar,
            'commenter_id' => $this->commenter->id,
            'commenter_username' => $this->commenter->username,
            'comment_id' => $this->comment->id,
            'comment_preview' => $commentPreview,
            'commentable_type' => $this->commentableType,
            'commentable_id' => $this->commentableId,
            'notification_type' => 'comment'
        ];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase($notifiable)
    {
        $contentType = $this->getContentTypeName();
        $commentPreview = strlen($this->comment->comment) > 100 
            ? substr($this->comment->comment, 0, 100) . '...' 
            : $this->comment->comment;

        return [
            'message' => "{$this->commenter->username} commented on your {$contentType}.",
            'sender_name' => "{$this->commenter->first_name} {$this->commenter->last_name}",
            'avatar' => $this->commenter->avatar,
            'commenter_id' => $this->commenter->id,
            'commenter_username' => $this->commenter->username,
            'comment_id' => $this->comment->id,
            'comment_preview' => $commentPreview,
            'commentable_type' => $this->commentableType,
            'commentable_id' => $this->commentableId,
        ];
    }

    /**
     * Get a friendly name for the commentable type.
     */
    private function getContentTypeName(): string
    {
        return match($this->commentableType) {
            'App\\Models\\Post' => 'post',
            'App\\Models\\Course' => 'course',
            'App\\Models\\LibraryUrl' => 'library content',
            default => 'content'
        };
    }
}
