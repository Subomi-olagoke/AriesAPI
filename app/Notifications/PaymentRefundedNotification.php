<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class PaymentRefundedNotification extends BaseNotification implements ShouldQueue
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
            'title' => $this->data['title'] ?? 'Payment Refunded',
            'body' => $this->data['body'] ?? 'Your payment has been refunded.',
            'data' => $this->data['data'] ?? [],
            'type' => $this->data['type'] ?? 'payment_refunded',
        ];
    }
}