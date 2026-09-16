<?php

namespace Tests\Unit;

use App\Jobs\SendAssetEmailMessageJob;
use App\Jobs\SendAssetNotificationJob;
use App\Jobs\SendAssetWhatsAppMessageJob;
use PHPUnit\Framework\TestCase;

class HorizonQueueJobsTest extends TestCase
{
    public function test_asset_jobs_use_the_notifications_queue(): void
    {
        $this->assertSame('notifications', (new SendAssetNotificationJob(1, 'Subject', 'Message'))->queue);
        $this->assertSame('notifications', (new SendAssetWhatsAppMessageJob('6281234567890', 'Halo'))->queue);
        $this->assertSame('notifications', (new SendAssetEmailMessageJob('ops@example.test', 'Subject', 'Body'))->queue);
    }
}
