<?php

namespace App\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;

class ContentFlaggedNotification extends BaseNotification implements ShouldQueue
{
    protected $reporter;
    protected $report;
    protected $content;
    protected $contentType;

    /**
     * Create a new notification instance.
     */
    public function __construct($reporter, $report, $content, $contentType)
    {
        $this->reporter = $reporter;
        $this->report = $report;
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
            'title' => 'Content Flagged',
            'message' => "Your {$contentTypeName} has been flagged for review.",
            'content_id' => $this->content->id ?? null,
            'content_title' => $contentTitle,
            'content_type' => $this->contentType,
            'report_id' => $this->report->id ?? null,
            'report_reason' => $this->report->reason ?? null,
            'notification_type' => 'content_flagged'
        ];
    }

    /**
     * Get a friendly name for the content type.
     */
    private function getContentTypeName(): string
    {
        return match($this->contentType) {
            'App\\Models\\Post' => 'post',
            'App\\Models\\Course' => 'course',
            'App\\Models\\LibraryUrl' => 'library content',
            'App\\Models\\Comment' => 'comment',
            'App\\Models\\User' => 'profile',
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
            ?? $this->content->username 
            ?? 'Untitled';
    }
}
