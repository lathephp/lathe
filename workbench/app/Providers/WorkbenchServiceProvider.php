<?php

namespace Workbench\App\Providers;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Workbench\App\Console\Commands\DemoSeedCommand;
use Workbench\App\Http\Middleware\StrictContentSecurityPolicy;

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

        // The served workbench runs under the strict policy; feature tests check tags without a nonce, so they opt out.
        config(['workbench.strict_csp' => config('workbench.strict_csp', ! $this->app->runningUnitTests())]);
        $this->app->make(Kernel::class)->pushMiddleware(StrictContentSecurityPolicy::class);
    }
}
