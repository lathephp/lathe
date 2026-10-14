<?php

namespace Lathe\Permission\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Lathe\Lathe;
use Lathe\Permission\PermissionPlugin;
use Lathe\Permission\PermissionServiceProvider;
use Lathe\Permission\Tests\Fixtures\TestUser;
use Lathe\Permission\Tests\Fixtures\TestUserResource;
use Lathe\Plugins\PluginRegistry;
use Lathe\Testing\TestCase as LatheTestCase;
use Spatie\Permission\PermissionServiceProvider as SpatieServiceProvider;

abstract class TestCase extends LatheTestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string<ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), SpatieServiceProvider::class, PermissionServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('auth.providers.users.model', TestUser::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Lathe::registerResources(TestUserResource::class);
        $this->registerPluginUnderTest();
    }

    /**
     * Register the plugin by hand, as a host provider does. The discovery tests override this.
     */
    protected function registerPluginUnderTest(): void
    {
        Lathe::registerPlugins(PermissionPlugin::make());
    }

    /**
     * Start over with the host's User Resource and this plugin instance alone, since the
     * registry keeps the first instance of a class and ignores the next.
     */
    protected function registerOnly(PermissionPlugin $plugin): PermissionPlugin
    {
        $this->app->forgetInstance(PluginRegistry::class);
        Lathe::resources()->reset();
        Lathe::registerResources(TestUserResource::class);
        Lathe::registerPlugins($plugin);

        return $plugin;
    }

    /**
     * A saved user who passes viewLathe, signed in.
     */
    protected function signInAdmin(): TestUser
    {
        $user = TestUser::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret']);

        $this->actingAs($user);

        return $user;
    }
}
