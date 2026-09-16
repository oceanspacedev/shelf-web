<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\AssetNotificationService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;

class SendAssetNotificationJob implements ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @param  string|array{whatsapp: string, email?: array<string, mixed>}  $message
     */
    public function __construct(
        public int $userId,
        public string $subject,
        public string|array $message,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        AssetNotificationService::send($user, $this->subject, $this->message);
    }
}
