<?php

namespace Tests\Feature;

use Illuminate\Support\Env;
use Tests\TestCase;

class Laravel12EnvironmentConfigTest extends TestCase
{
    public function test_phpunit_xml_cache_store_and_mail_scheme_drive_config(): void
    {
        $this->assertSame('array', config('cache.default'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertArrayNotHasKey('encryption', config('mail.mailers.smtp'));
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
    }

    public function test_laravel_12_env_names_win_over_legacy_names(): void
    {
        $this->withEnv([
            'CACHE_STORE' => 'redis',
            'CACHE_DRIVER' => 'file',
            'MAIL_SCHEME' => 'smtps',
            'MAIL_ENCRYPTION' => 'tls',
            'APP_LOCALE' => 'en',
            'APP_FALLBACK_LOCALE' => 'id',
            'APP_FAKER_LOCALE' => 'en_US',
            'APP_MAINTENANCE_DRIVER' => 'cache',
            'BCRYPT_ROUNDS' => '7',
            'LOG_STACK' => 'daily,stderr',
            'REDIS_CLIENT' => 'predis',
            'SESSION_ENCRYPT' => 'true',
            'SESSION_PATH' => '/shelf',
            'SESSION_DOMAIN' => 'shelf.test',
            'AWS_ACCESS_KEY_ID' => 'aws-key',
            'MINIO_ACCESS_KEY_ID' => 'minio-key',
            'AWS_SECRET_ACCESS_KEY' => 'aws-secret',
            'MINIO_SECRET_ACCESS_KEY' => 'minio-secret',
            'AWS_DEFAULT_REGION' => 'ap-southeast-1',
            'MINIO_DEFAULT_REGION' => 'us-east-1',
            'AWS_BUCKET' => 'aws-bucket',
            'MINIO_BUCKET' => 'minio-bucket',
            'AWS_ENDPOINT' => 'https://s3.example.test',
            'MINIO_ENDPOINT' => 'http://127.0.0.1:9000',
        ], function (): void {
            config([
                'app' => require config_path('app.php'),
                'cache' => require config_path('cache.php'),
                'mail' => require config_path('mail.php'),
                'hashing' => require config_path('hashing.php'),
                'logging' => require config_path('logging.php'),
                'database' => require config_path('database.php'),
                'session' => require config_path('session.php'),
                'filesystems' => require config_path('filesystems.php'),
            ]);

            $this->assertSame('redis', config('cache.default'));
            $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
            $this->assertArrayNotHasKey('encryption', config('mail.mailers.smtp'));
            $this->assertSame('en', config('app.locale'));
            $this->assertSame('id', config('app.fallback_locale'));
            $this->assertSame('en_US', config('app.faker_locale'));
            $this->assertSame('Asia/Jakarta', config('app.timezone'));
            $this->assertSame('cache', config('app.maintenance.driver'));
            $this->assertSame(7, (int) config('hashing.bcrypt.rounds'));
            $this->assertSame(['daily', 'stderr'], config('logging.channels.stack.channels'));
            $this->assertSame('predis', config('database.redis.client'));
            $this->assertTrue(config('session.encrypt'));
            $this->assertSame('/shelf', config('session.path'));
            $this->assertSame('shelf.test', config('session.domain'));
            $this->assertSame('aws-key', config('filesystems.disks.s3.key'));
            $this->assertSame('aws-secret', config('filesystems.disks.s3.secret'));
            $this->assertSame('ap-southeast-1', config('filesystems.disks.s3.region'));
            $this->assertSame('aws-bucket', config('filesystems.disks.s3.bucket'));
            $this->assertSame('https://s3.example.test', config('filesystems.disks.s3.endpoint'));
        });
    }

    public function test_s3_disk_falls_back_to_minio_env_names(): void
    {
        $this->withEnv([
            'AWS_ACCESS_KEY_ID' => null,
            'AWS_SECRET_ACCESS_KEY' => null,
            'AWS_DEFAULT_REGION' => null,
            'AWS_BUCKET' => null,
            'AWS_ENDPOINT' => null,
            'AWS_URL' => null,
            'AWS_USE_PATH_STYLE_ENDPOINT' => null,
            'MINIO_ACCESS_KEY_ID' => 'minio-key',
            'MINIO_SECRET_ACCESS_KEY' => 'minio-secret',
            'MINIO_DEFAULT_REGION' => 'us-east-1',
            'MINIO_BUCKET' => 'minio-bucket',
            'MINIO_ENDPOINT' => 'http://127.0.0.1:9000',
            'MINIO_URL' => 'http://127.0.0.1:9000/minio-bucket',
            'MINIO_USE_PATH_STYLE_ENDPOINT' => 'true',
        ], function (): void {
            config([
                'filesystems' => require config_path('filesystems.php'),
            ]);

            $this->assertSame('minio-key', config('filesystems.disks.s3.key'));
            $this->assertSame('minio-secret', config('filesystems.disks.s3.secret'));
            $this->assertSame('us-east-1', config('filesystems.disks.s3.region'));
            $this->assertSame('minio-bucket', config('filesystems.disks.s3.bucket'));
            $this->assertSame('http://127.0.0.1:9000', config('filesystems.disks.s3.endpoint'));
            $this->assertSame('http://127.0.0.1:9000/minio-bucket', config('filesystems.disks.s3.url'));
            $this->assertTrue((bool) config('filesystems.disks.s3.use_path_style_endpoint'));
        });
    }

    /**
     * @param  array<string, string|null>  $variables
     */
    private function withEnv(array $variables, callable $callback): void
    {
        $previous = [];

        foreach ($variables as $key => $value) {
            $previous[$key] = [
                'env' => array_key_exists($key, $_ENV) ? $_ENV[$key] : null,
                'server' => array_key_exists($key, $_SERVER) ? $_SERVER[$key] : null,
                'putenv' => getenv($key),
                'had_env' => array_key_exists($key, $_ENV),
                'had_server' => array_key_exists($key, $_SERVER),
            ];

            if ($value === null) {
                unset($_ENV[$key], $_SERVER[$key]);
                putenv($key);

                continue;
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key.'='.$value);
        }

        Env::enablePutenv();

        try {
            $callback();
        } finally {
            foreach ($previous as $key => $state) {
                if ($state['had_env']) {
                    $_ENV[$key] = $state['env'];
                } else {
                    unset($_ENV[$key]);
                }

                if ($state['had_server']) {
                    $_SERVER[$key] = $state['server'];
                } else {
                    unset($_SERVER[$key]);
                }

                if ($state['putenv'] === false) {
                    putenv($key);
                } else {
                    putenv($key.'='.$state['putenv']);
                }
            }

            Env::enablePutenv();
        }
    }
}
