<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class AssetNotificationMail extends Mailable
{
    public function __construct(
        public string $subjectLine,
        public string $body,
    ) {}

    public function build(): self
    {
        return $this
            ->subject($this->subjectLine)
            ->text('emails.asset-notification');
    }
}
