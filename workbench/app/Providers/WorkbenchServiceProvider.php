<?php

namespace Workbench\App\Providers;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Features;
use Lathe\Assets\ExtensionManifest;
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

        // The served workbench confirms two-factor with a code and asks for the password first;
        // tests choose these options themselves.
        if (! $this->app->runningUnitTests()) {
            Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]);
        }
        $kernel = $this->app->make(Kernel::class)
            ->pushMiddleware(StrictContentSecurityPolicy::class)
            ->pushMiddleware(ServerTiming::class)
            ->pushMiddleware(StripedTables::class)
            ->pushMiddleware(ArabicContext::class);

        if (getenv('LATHE_OCTANE_LANE') === '1') {
            $kernel->pushMiddleware(WorkerMemory::class);
        }

        // The Octane lane's Arabic context reads right to left.
        Lathe::enableRTL(fn (Request $request): bool => Lathe::resolveUserLocale($request) === 'ar');

        // The Octane lane's Kiritimati context names its timezone in a header.
        Lathe::userTimezone(fn (Request $request): ?string => $request->header('X-Bench-Timezone'));

        // The live demo prints its sign-in under the login form; resources/js/lathe/demoLogin.tsx draws it.
        // The flag is read from the environment, so the demo app runs without config:cache.
        if (getenv('LATHE_DEMO') === 'true') {
            // The demo flag also silences the render hook badges in resources/js/lathe/renderHooks.tsx.
            Lathe::provideToScript(['demo' => true, 'demoLogin' => ['email' => 'admin@lathephp.com', 'password' => 'password']]);
        }

        $this->registerExtensions();
    }

    /**
     * Testbench's public path sits inside vendor, so the workbench commits its lathe:build output
     * under workbench/public and serves the entry through the panel's module route. lathe:doctor
     * reads the manifest.json next to it for the keys the build registers. A deployed app holds a copy
     * under its own public path, which Lathe already serves, so registering it again loads the boot script twice.
     */
    protected function registerExtensions(): void
    {
        if (is_file(public_path(ExtensionManifest::PUBLIC_DIRECTORY.'/manifest.json'))) {
            return;
        }

        $folder = dirname(__DIR__, 2).'/public/vendor/lathe/extensions';
        $manifest = is_file("{$folder}/manifest.json") ? json_decode((string) file_get_contents("{$folder}/manifest.json"), true) : null;
        $entry = is_array($manifest) ? ($manifest['entry'] ?? null) : null;

        if (is_string($entry)) {
            Lathe::script('workbench-extensions', "{$folder}/{$entry}");
        }

        foreach (is_array($manifest) && is_array($manifest['styles'] ?? null) ? $manifest['styles'] : [] as $index => $style) {
            if (is_string($style)) {
                Lathe::style("workbench-extensions-{$index}", "{$folder}/{$style}");
            }
        }
    }
}
