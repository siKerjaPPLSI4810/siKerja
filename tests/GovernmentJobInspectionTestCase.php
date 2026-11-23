<?php

namespace Tests;

use App\Models\CompanyProfile;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

abstract class GovernmentJobInspectionTestCase extends TestCase
{
    private const CONNECTION = 'job_inspection_testing';

    public function createApplication()
    {
        $temporary = sys_get_temp_dir().'/hasya-job-inspection-'.bin2hex(random_bytes(8));
        foreach (['environment', 'storage/framework/views', 'storage/framework/cache', 'storage/framework/sessions', 'storage/logs', 'cache'] as $directory) {
            if (! mkdir($temporary.'/'.$directory, 0700, true) && ! is_dir($temporary.'/'.$directory)) {
                throw new \RuntimeException('Cannot create isolated testing directory.');
            }
        }
        // Process-local values only. Never load the repository .env or cached configuration.
        foreach ([
            'APP_ENV' => 'testing', 'APP_URL' => 'http://localhost',
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'APP_CONFIG_CACHE' => $temporary.'/cache/config.php',
            'APP_ROUTES_CACHE' => $temporary.'/cache/routes.php',
            'APP_PACKAGES_CACHE' => $temporary.'/cache/packages.php',
            'APP_SERVICES_CACHE' => $temporary.'/cache/services.php',
            'APP_EVENTS_CACHE' => $temporary.'/cache/events.php',
            'LARAVEL_STORAGE_PATH' => $temporary.'/storage',
            'VIEW_COMPILED_PATH' => $temporary.'/storage/framework/views',
            'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
            'DB_HOST' => '127.0.0.1', 'DB_USERNAME' => '', 'DB_PASSWORD' => '',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
        ] as $name => $value) {
            putenv($name.'='.$value);
            $_ENV[$name] = $_SERVER[$name] = $value;
        }

        $app = require dirname(__DIR__).'/bootstrap/app.php';
        $app->useEnvironmentPath($temporary.'/environment');
        $app->useStoragePath($temporary.'/storage');
        $app->addAbsoluteCachePathPrefix(substr($temporary, 0, 3));
        $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app): void {
            $app['config']->set('database.default', 'sqlite');
            $app['config']->set('database.connections', [
                'sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
            ]);
        });
        $this->traitsUsedByTest = class_uses_recursive(static::class);
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        if (! $app->environment('testing') || $app['config']->get('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Unsafe testing environment.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (! $this->app->environment('testing')) {
            throw new \RuntimeException('APP_ENV=testing is required.');
        }
        config([
            'app.key' => str_repeat('a', 32), 'cache.default' => 'array', 'session.driver' => 'array',
            'database.default' => self::CONNECTION,
            'database.connections.'.self::CONNECTION => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
            ],
        ]);
        $this->beforeApplicationDestroyed(fn () => DB::purge(self::CONNECTION));
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
        // Real migrations, applied only after verifying the dedicated SQLite connection.
        foreach (['0001_01_01_000000_create_users_table.php', '2026_11_06_073058_create_sikerja_table.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
    }

    protected function admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'pemerintah', 'is_active' => true]));
    }

    protected function listing(array $changes = [], ?CompanyProfile $company = null): JobPosting
    {
        $company ??= CompanyProfile::factory()->create();

        return JobPosting::factory()->create(array_merge([
            'company_profile_id' => $company->id, 'title' => 'Lowongan antrean',
        ], $changes));
    }

}
