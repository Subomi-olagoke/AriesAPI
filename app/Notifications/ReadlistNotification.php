<?php

namespace App\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;

class ReadlistNotification extends BaseNotification implements ShouldQueue
{
    protected $adder;
    protected $readlist;
    protected $content;
    protected $contentType;

    /**
     * Create a new notification instance.
     */
    public function __construct($adder, $readlist, $content, $contentType)
    {
        $this->adder = $adder;
        $this->readlist = $readlist;
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
            'title' => 'Added to Read List',
            'message' => "{$this->adder->username} added your {$contentTypeName} to their read list \"{$this->readlist->name}\".",
            'sender_name' => "{$this->adder->first_name} {$this->adder->last_name}",
            'avatar' => $this->adder->avatar,
            'adder_id' => $this->adder->id,
            'adder_username' => $this->adder->username,
            'readlist_id' => $this->readlist->id,
            'readlist_name' => $this->readlist->name,
            'content_id' => $this->content->id ?? null,
            'content_title' => $contentTitle,
            'content_type' => $this->contentType,
            'notification_type' => 'readlist'
        ];
    }

    /**
     * Get a friendly name for the content type.
     */
    private function getContentTypeName(): string
    {
        return match($this->contentType) {
            'post' => 'post',
            'course' => 'course',
            'url' => 'link',
            'library_url' => 'link',
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
