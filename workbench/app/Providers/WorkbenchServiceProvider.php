<?php

namespace Workbench\App\Providers;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Lathe\Lathe;
use Workbench\App\Console\Commands\DemoSeedCommand;
use Workbench\App\Http\Middleware\ArabicContext;
use Workbench\App\Http\Middleware\ServerTiming;
use Workbench\App\Http\Middleware\StrictContentSecurityPolicy;
use Workbench\App\Http\Middleware\StripedTables;
use Workbench\App\Http\Middleware\WorkerMemory;

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
        $kernel = $this->app->make(Kernel::class)
            ->pushMiddleware(StrictContentSecurityPolicy::class)
            ->pushMiddleware(ServerTiming::class)
            ->pushMiddleware(StripedTables::class)
            ->pushMiddleware(ArabicContext::class);

        if (getenv('LATHE_OCTANE_LANE') === '1') {
            $kernel->pushMiddleware(WorkerMemory::class);
        }

        // The Octane lane's Kiritimati context names its timezone in a header.
        Lathe::userTimezone(fn (Request $request): ?string => $request->header('X-Bench-Timezone'));
    }
}
