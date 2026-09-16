<?php

namespace App\Jobs;

use App\Services\WhatsappService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class SendAssetWhatsAppMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public string $phoneNumber,
        public string $message,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        if (! WhatsappService::send($this->phoneNumber, $this->message)) {
            throw new RuntimeException('WhatsApp gateway rejected the asset reminder.');
        }
    }
}
