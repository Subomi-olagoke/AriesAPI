<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WaitlistMail extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;
    public $content;
    public $recipientName;

    /**
     * Create a new message instance.
     *
     * @param string $subject
     * @param string $content
     * @param string $recipientName
     * @return void
     */
    public function __construct($subject, $content, $recipientName)
    {
        $this->subject = $subject;
        $this->content = $content;
        $this->recipientName = $recipientName;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject($this->subject)
            ->view('emails.waitlist')
            ->with([
                'content' => $this->content,
                'recipientName' => $this->recipientName
            ]);
    }
}