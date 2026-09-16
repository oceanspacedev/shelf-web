<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendAssetEmailMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public string $email,
        public string $subject,
        public string $message,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        Mail::raw($this->message, function ($mail): void {
            $mail->to($this->email)->subject($this->subject);
        });
    }
}
