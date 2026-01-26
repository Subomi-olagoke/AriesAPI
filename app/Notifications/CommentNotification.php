<?php

namespace App\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;

class CommentNotification extends BaseNotification implements ShouldQueue
{

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
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        $contentType = $this->getContentTypeName();
        $commentText = $this->comment->body ?? $this->comment->comment ?? '';
        $commentPreview = strlen($commentText) > 100 
            ? substr($commentText, 0, 100) . '...' 
            : $commentText;

        return [
            'title' => 'New Comment',
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
     * Get a friendly name for the commentable type.
     */
    private function getContentTypeName(): string
    {
        return match($this->commentableType) {
            'App\\Models\\Post' => 'post',
            'App\\Models\\Course' => 'course',
            'App\\Models\\LibraryUrl' => 'library content',
            'App\\Models\\Comment' => 'comment',
            default => 'content'
        };
    }
}
