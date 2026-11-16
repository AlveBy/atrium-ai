<?php

declare(strict_types=1);

namespace Atrium\Ai\Tests;

use Atrium\Admin\AtriumServiceProvider;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Ai\AiServiceProvider;
use Atrium\Ai\Tests\Fixtures\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Laravel\Ai\AiServiceProvider as SdkServiceProvider;
use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Tighten\Ziggy\ZiggyServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            InertiaServiceProvider::class,
            ZiggyServiceProvider::class,
            McpServiceProvider::class,
            SdkServiceProvider::class,
            AtriumServiceProvider::class,
            AiServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $config = $app['config'];
        $config->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $config->set('app.name', 'Demo shop');
        // CI runs the suite against MySQL and PostgreSQL too.
        $target = env('DB_CONNECTION');
        $config->set('database.default', in_array($target, ['mysql', 'pgsql'], true) ? $target : 'testing');
        $config->set('atrium.demo', false);
        $config->set('atrium.auth.two_factor.enabled', false);
        $config->set('atrium.dashboard.timings.enabled', false);
        $config->set('atrium.modules', [AiServiceProvider::MODULE]);
        $config->set('atrium.resources', [Article::class]);
        // Keys of the free providers, as a user would put them into .env. The
        // SDK's fakes answer: nothing goes to the network.
        $config->set('ai.providers.gemini.key', 'test-gemini-key');
        $config->set('ai.providers.groq.key', 'test-groq-key');
        $config->set('ai.providers.openrouter.key', null);
        $config->set('queue.default', 'sync');
        // The answer in the first response; the default (after the response) has its own test.
        $config->set('atrium-ai.queue.connection', 'sync');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    protected function admin(): AdminUser
    {
        $admin = AdminUser::query()->firstOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Admin',
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        $admin->forceFill(['is_superadmin' => true])->save();

        return $admin;
    }

    /**
     * @param  array<int,string>  $permissions
     */
    protected function editor(array $permissions): AdminUser
    {
        $user = AdminUser::query()->create([
            'name' => 'Editor',
            'email' => 'editor@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);
        $user->roles()->attach(AdminRole::query()->create(['name' => 'Role', 'permissions' => $permissions]));

        return $user;
    }
}
