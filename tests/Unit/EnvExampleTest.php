<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class EnvExampleTest extends TestCase
{
    public function test_env_example_documents_laravel_12_and_shelf_keys(): void
    {
        $path = dirname(__DIR__, 2).'/.env.example';
        $contents = file_get_contents($path);

        $this->assertIsString($contents);

        $keys = $this->uncommentedKeys($contents);

        foreach ([
            'CACHE_STORE',
            'MAIL_SCHEME',
            'BROADCAST_CONNECTION',
            'APP_LOCALE',
            'APP_FALLBACK_LOCALE',
            'APP_FAKER_LOCALE',
            'APP_MAINTENANCE_DRIVER',
            'BCRYPT_ROUNDS',
            'LOG_STACK',
            'REDIS_CLIENT',
            'SESSION_ENCRYPT',
            'SESSION_PATH',
            'SESSION_DOMAIN',
            'AWS_ACCESS_KEY_ID',
            'AWS_SECRET_ACCESS_KEY',
            'AWS_DEFAULT_REGION',
            'AWS_BUCKET',
            'AWS_ENDPOINT',
            'AWS_URL',
            'AWS_USE_PATH_STYLE_ENDPOINT',
            'VITE_APP_NAME',
        ] as $key) {
            $this->assertContains($key, $keys, "{$key} must be an uncommented key in .env.example");
        }

        foreach ([
            'CACHE_DRIVER',
            'MAIL_ENCRYPTION',
            'WHATSAPP_GATEWAY_PROVIDER',
            'WHATSAPP_GATEWAY_WAHA_BASE_URL',
            'WHATSAPP_GATEWAY_FONNTE_TOKEN',
            'FONNTE_TOKEN',
            'WAHA_API_KEY',
            'FILESYSTEM_DRIVER',
            'BROADCAST_DRIVER',
            'QUEUE_DRIVER',
            'APP_TIMEZONE',
            'ASSET_URL',
            'MINIO_ACCESS_KEY_ID',
            'MINIO_SECRET_ACCESS_KEY',
            'MINIO_DEFAULT_REGION',
            'MINIO_BUCKET',
            'MINIO_URL',
            'MINIO_ENDPOINT',
            'MINIO_USE_PATH_STYLE_ENDPOINT',
            'PUSHER_APP_ID',
            'PUSHER_APP_KEY',
            'PUSHER_APP_SECRET',
            'PUSHER_HOST',
            'PUSHER_PORT',
            'PUSHER_SCHEME',
            'PUSHER_APP_CLUSTER',
            'VITE_PUSHER_APP_KEY',
            'VITE_PUSHER_HOST',
            'VITE_PUSHER_PORT',
            'VITE_PUSHER_SCHEME',
            'VITE_PUSHER_APP_CLUSTER',
        ] as $key) {
            $this->assertDoesNotMatchRegularExpression(
                '/^#?\s*'.preg_quote($key, '/').'=/m',
                $contents,
                "{$key} should not appear in .env.example"
            );
            $this->assertNotContains($key, $keys);
        }

        foreach ([
            'APP_NAME',
            'WAG_URL',
            'WAG_TOKEN',
            'WA_CONNECT_TIMEOUT',
            'WA_API_TIMEOUT',
            'WHATSAPP_DEFAULT_TARGET',
            'HORIZON_PATH',
            'LOG_VIEWER_ENABLED',
            'LOG_VIEWER_PATH',
        ] as $key) {
            $this->assertContains($key, $keys, "{$key} must remain in .env.example");
        }

        $this->assertMatchesRegularExpression('/^\s*#\s*APP_MAINTENANCE_STORE=/m', $contents);
        $this->assertMatchesRegularExpression('/^\s*#\s*PHP_CLI_SERVER_WORKERS=/m', $contents);
        $this->assertMatchesRegularExpression('/^\s*#\s*CACHE_PREFIX=/m', $contents);
        $this->assertMatchesRegularExpression('/^\s*#\s*SEED_SUPER_ADMIN_USERNAME=/m', $contents);
        $this->assertMatchesRegularExpression('/^\s*#\s*SEED_SUPER_ADMIN_PASSWORD=/m', $contents);
        $this->assertMatchesRegularExpression('/^CACHE_STORE=file$/m', $contents);
        $this->assertMatchesRegularExpression('/^MAIL_MAILER=log$/m', $contents);
        $this->assertMatchesRegularExpression('/^BROADCAST_CONNECTION=log$/m', $contents);
        $this->assertMatchesRegularExpression('/^DB_CONNECTION=mysql$/m', $contents);
    }

    /**
     * @return list<string>
     */
    private function uncommentedKeys(string $contents): array
    {
        $keys = [];

        foreach (preg_split('/\r\n|\r|\n/', $contents) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $keys[] = explode('=', $line, 2)[0];
        }

        return $keys;
    }
}
