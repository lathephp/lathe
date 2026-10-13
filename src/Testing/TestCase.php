<?php

namespace Lathe\Testing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Laravel\Fortify\FortifyServiceProvider;
use Lathe\LatheServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * The base class for a host app's panel tests. Its viewLathe gate admits every signed-in user;
 * a test of the gate itself redefines it with Gate::define.
 */
abstract class TestCase extends Orchestra
{
    use InteractsWithLathe;

    /**
     * @param  Application  $app
     * @return list<class-string<ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [InertiaServiceProvider::class, FortifyServiceProvider::class, LatheServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading();
        Gate::define('viewLathe', fn () => true);
    }

    protected function tearDown(): void
    {
        $this->flushLathe();
        Model::preventLazyLoading(false);

        parent::tearDown();
    }
}
