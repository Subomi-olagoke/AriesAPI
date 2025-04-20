<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class VerificationStatusNotification extends BaseNotification implements ShouldQueue
{
    use Queueable;

    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function toArray()
    {
        return [
            'title' => $this->data['title'] ?? 'Verification Status Update',
            'body' => $this->data['body'] ?? 'Your verification status has been updated.',
            'data' => $this->data['data'] ?? [],
            'type' => $this->data['type'] ?? 'verification_status_update',
        ];
    }
}