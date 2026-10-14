<?php

use Acme\PriceTracker\PriceTracker;
use Composer\Autoload\ClassLoader;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Lathe\Lathe;
use Lathe\Tests\Feature\Console\Generators\GeneratesIntoTempApp;
use Symfony\Component\Process\Process;
use Workbench\App\Models\User;

pest()->group('scaffold');

uses(GeneratesIntoTempApp::class);

test('generated_plugin_builds_and_boots', function () {
    holdBuildLock();
    $repo = dirname(__DIR__, 3);
    // Every step shares one 30 minute budget.
    $deadline = time() + 1800;
    $base = $this->generatorApp();
    $this->app->instance('lathe.host_path', $base);

    $this->artisan('lathe:plugin', ['name' => 'acme/price-tracker', '--skip-composer' => true])->assertExitCode(0);
    $plugin = "{$base}/lathe-components/PriceTracker";

    // The workspace package that Heartbeat builds with holds the same dev dependencies the
    // skeleton names, so the generated package links to its entries instead of installing them.
    $modules = "{$repo}/workbench/lathe-components/Heartbeat/node_modules";
    File::ensureDirectoryExists("{$plugin}/node_modules");

    foreach (array_diff((array) scandir($modules), ['.', '..', '.vite-temp']) as $entry) {
        symlink("{$modules}/{$entry}", "{$plugin}/node_modules/{$entry}");
    }

    if (! is_file("{$repo}/packages/vite-plugin/dist/index.js")) {
        (new Process(['bash', 'scripts/pnpm.sh', '--config.verify-deps-before-run=false', '--filter', '@lathephp/vite-plugin', 'build'], $repo, timeout: $deadline - time()))->mustRun();
    }

    $build = new Process(['bash', "{$repo}/scripts/pnpm.sh", '--config.verify-deps-before-run=false', 'exec', 'vite', 'build'], $plugin, timeout: $deadline - time());
    $build->run();

    expect($build->getExitCode())->toBe(0, $build->getOutput().$build->getErrorOutput())
        ->and(json_decode(File::get("{$plugin}/dist/lathe-plugin.json"), true)['components'])->toBe(['plugin' => ['price-tracker']]);

    // Installed by path: the class loads from the generated src/ and the panel registers it.
    ClassLoader::getRegisteredLoaders()[array_key_first(ClassLoader::getRegisteredLoaders())]->addPsr4('Acme\\PriceTracker\\', "{$plugin}/src/");
    Lathe::registerPlugins(new PriceTracker);
    reloadLatheRoutes();

    Gate::define('viewLathe', fn () => true);
    $this->actingAs((new User)->forceFill(['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.test']));

    $this->getJson('/lathe/api/plugins/price-tracker/ping')->assertOk()->assertExactJson(['pong' => true]);

    $module = '/lathe/modules/price-tracker-'.substr((string) hash_file('sha256', "{$plugin}/dist/price-tracker.js"), 0, 8).'.js';

    $this->get('/lathe/plugins/price-tracker')->assertOk()->assertSee($module, false);
    $this->get($module)->assertOk();
})->group('regression');
