<?php

namespace App\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;

class LibraryContentAddedNotification extends BaseNotification implements ShouldQueue
{
    protected $adder;
    protected $library;
    protected $content;
    protected $contentType;

    /**
     * Create a new notification instance.
     */
    public function __construct($adder, $library, $content, $contentType)
    {
        $this->adder = $adder;
        $this->library = $library;
        $this->content = $content;
        $this->contentType = $contentType;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        $contentTitle = $this->getContentTitle();
        $contentTypeName = $this->getContentTypeName();

        return [
            'title' => 'New Content in Library',
            'message' => "{$this->adder->username} added {$contentTypeName} to \"{$this->library->name}\".",
            'sender_name' => "{$this->adder->first_name} {$this->adder->last_name}",
            'avatar' => $this->adder->avatar,
            'adder_id' => $this->adder->id,
            'adder_username' => $this->adder->username,
            'library_id' => $this->library->id,
            'library_name' => $this->library->name,
            'content_id' => $this->content->id ?? null,
            'content_title' => $contentTitle,
            'content_type' => $this->contentType,
            'notification_type' => 'library_content_added'
        ];
    }

    /**
     * Get a friendly name for the content type.
     */
    private function getContentTypeName(): string
    {
        return match($this->contentType) {
            'post' => 'a post',
            'course' => 'a course',
            'url' => 'a link',
            'library_url' => 'a link',
            default => 'content'
        };
    }

    /**
     * Get the content title.
     */
    private function getContentTitle(): ?string
    {
        if (!$this->content) {
            return null;
        }

        return $this->content->title 
            ?? $this->content->name 
            ?? $this->content->url 
            ?? 'Untitled';
    }
}
