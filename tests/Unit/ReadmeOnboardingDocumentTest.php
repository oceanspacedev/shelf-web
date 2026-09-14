<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ReadmeOnboardingDocumentTest extends TestCase
{
    private string $readme;

    protected function setUp(): void
    {
        parent::setUp();

        $path = dirname(__DIR__, 2).'/README.md';

        $this->assertFileExists($path, 'Root README.md must exist.');

        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Root README.md must be readable.');
        $this->assertNotSame('', trim($contents), 'Root README.md must not be empty.');

        $this->readme = $contents;
    }

    public function test_readme_has_required_sister_repo_skeleton_headings(): void
    {
        foreach ([
            '## Daftar isi',
            '## Tujuan dan scope',
            '## Fitur utama',
            '## Role dan hak akses',
            '## Cara kerja aplikasi',
            '## Arsitektur',
            '## Tech stack',
            '## Persiapan development',
            '## Konfigurasi environment',
            '## Menjalankan aplikasi',
            '## Workflow development',
            '## Testing dan quality check',
            '## Batasan dan technical debt',
            '## Troubleshooting',
        ] as $heading) {
            $this->assertStringContainsString($heading, $this->readme);
        }

        $this->assertStringContainsString('[Tujuan dan scope](#tujuan-dan-scope)', $this->readme);
        $this->assertStringContainsString('> [!IMPORTANT]', $this->readme);
        $this->assertStringContainsString('> [!WARNING]', $this->readme);
        $this->assertMatchesRegularExpression('/```mermaid\s+flowchart /s', $this->readme);
        $this->assertGreaterThanOrEqual(
            2,
            preg_match_all('/```mermaid\s+flowchart /s', $this->readme),
            'Cara kerja and arsitektur must each include a mermaid flowchart.',
        );
        $this->assertStringContainsString('public/images/icon.svg', $this->readme);
        $this->assertDoesNotMatchRegularExpression('/^## Requirements/m', $this->readme);
        $this->assertDoesNotMatchRegularExpression('/^## Installation/m', $this->readme);
    }

    public function test_readme_describes_this_shelf_app_from_repo_facts(): void
    {
        foreach ([
            'https://github.com/oceanspacedev/shelf-web.git',
            '/admin',
            'asset-requests',
            'Asia/Jakarta',
            'notifications:send-scheduled',
            'admin@dev.com',
            'locale `id`',
            'APP_NAME=Shelf',
            'composer install',
            'npm ci',
            'php artisan key:generate',
            'php artisan migrate --seed',
            'php artisan storage:link',
            'php artisan serve',
            'npm run dev',
            'php artisan test',
            'vendor/bin/pint --test',
            'migrate:fresh',
            'db:wipe',
            'WHATSAPP_GATEWAY_PROVIDER',
            'SEED_SUPER_ADMIN_',
            'MINIO_',
            'general_affair',
            'super_admin',
            '/up',
            'username',
            'password',
        ] as $fact) {
            $this->assertStringContainsString($fact, $this->readme);
        }

        $this->assertStringContainsString('Jangan menjalankan `composer update`', $this->readme);
        $this->assertStringNotContainsString('CS-BusinessDev/web-shelf', $this->readme);
    }

    public function test_readme_does_not_present_helpdesk_or_dnd_product_identity(): void
    {
        foreach ([
            'git clone https://github.com/oceanspacedev/helpdesk-web.git',
            'git clone https://github.com/oceanspacedev/dnd-web.git',
            '/mcp/helpdesk',
            'sla:check-warnings',
            'kpi:send-reminders',
            'Form tiket publik',
            'Sistem helpdesk berbasis web',
            'KPI bulanan',
            '/api/v1',
            '## MCP opsional',
            '## Scheduler SLA',
            '## API dan dokumentasi',
            '## Deployment Docker',
            'compose.yaml',
        ] as $foreignIdentity) {
            $this->assertStringNotContainsString($foreignIdentity, $this->readme);
        }
    }
}
