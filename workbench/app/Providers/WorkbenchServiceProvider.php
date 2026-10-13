<?php

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;
use Lathe\Lathe;
use Workbench\App\Console\Commands\DemoSeedCommand;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->commands([DemoSeedCommand::class]);

        Lathe::discoverResources(dirname(__DIR__).'/Lathe/Resources', 'Workbench\\App\\Lathe\\Resources');
    }
}
