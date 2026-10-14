<?php

use Illuminate\Support\Facades\File;
use Lathe\Tests\Feature\Console\Generators\GeneratesIntoTempApp;
use Symfony\Component\Process\Process;

pest()->group('scaffold');

uses(GeneratesIntoTempApp::class);

/**
 * Asks the booted panel for the status, ping and page of the generated Heartbeat. It runs in
 * its own process, so the class loads from the generated src/ and not from the workbench copy.
 */
const HEARTBEAT_PANEL_TEST = <<<'PHP'
<?php

use Acme\Heartbeat\Heartbeat;
use Acme\Heartbeat\Tests\TestCase;
use Illuminate\Foundation\Auth\User;

uses(TestCase::class);

it('serves the generated plugin in the panel', function () {
    $this->actingAs((new User)->forceFill(['id' => 1, 'email' => 'ada@example.test']));
    $module = '/lathe/modules/heartbeat-'.substr((string) hash_file('sha256', __DIR__.'/../../dist/heartbeat.js'), 0, 8).'.js';

    expect((new ReflectionClass(Heartbeat::class))->getFileName())->toBe(realpath(__DIR__.'/../../src/Heartbeat.php'));

    $status = $this->getJson('/lathe/api/plugins/heartbeat/status')->assertOk()->json();

    expect(array_keys($status))->toBe(['app', 'php', 'laravel', 'database', 'time'])
        ->and($status['database'])->toBe('connected');

    $this->getJson('/lathe/api/plugins/heartbeat/ping')->assertOk()->assertExactJson(['pong' => true]);
    $this->get('/lathe/plugins/heartbeat')->assertOk()->assertSee($module, false);
    $this->get($module)->assertOk();
});
PHP;

it('rebuilds Heartbeat from the generator and serves it in the panel', function () {
    holdBuildLock();
    $repo = dirname(__DIR__, 3);
    // Every step shares one 30 minute budget.
    $deadline = time() + 1800;
    $base = $this->generatorApp();
    $this->app->instance('lathe.host_path', $base);

    $this->artisan('lathe:plugin', ['name' => 'acme/heartbeat', '--skip-composer' => true])->assertExitCode(0);
    $package = "{$base}/lathe-components/Heartbeat";

    // The example edits: the status endpoint and the page of the hand-built Heartbeat.
    $example = "{$repo}/workbench/lathe-components/Heartbeat";
    File::copy("{$example}/src/Heartbeat.php", "{$package}/src/Heartbeat.php");
    File::copy("{$example}/resources/js/plugin.tsx", "{$package}/resources/js/plugin.tsx");

    // The skeleton's dev dependencies are the ones the workspace's Heartbeat holds.
    $modules = "{$example}/node_modules";
    File::ensureDirectoryExists("{$package}/node_modules");

    foreach (array_diff((array) scandir($modules), ['.', '..', '.vite-temp']) as $entry) {
        symlink("{$modules}/{$entry}", "{$package}/node_modules/{$entry}");
    }

    if (! is_file("{$repo}/packages/vite-plugin/dist/index.js")) {
        (new Process(['bash', 'scripts/pnpm.sh', '--config.verify-deps-before-run=false', '--filter', '@lathephp/vite-plugin', 'build'], $repo, timeout: $deadline - time()))->mustRun();
    }

    $build = new Process(['bash', "{$repo}/scripts/pnpm.sh", '--config.verify-deps-before-run=false', 'exec', 'vite', 'build'], $package, timeout: $deadline - time());
    $build->run();

    expect($build->getExitCode())->toBe(0, $build->getOutput().$build->getErrorOutput());

    // Installed by path: this autoloader runs before Pest and finds Acme\Heartbeat in the generated package.
    File::put("{$base}/bootstrap.php", implode("\n", [
        '<?php',
        '',
        '$loader = require '.var_export("{$repo}/vendor/autoload.php", true).';',
        '$loader->addPsr4(\'Acme\\\\Heartbeat\\\\\', '.var_export("{$package}/src/", true).', true);',
        '$loader->addPsr4(\'Acme\\\\Heartbeat\\\\Tests\\\\\', '.var_export("{$package}/tests/", true).', true);',
        '',
    ]));
    File::ensureDirectoryExists("{$package}/tests/Panel");
    File::put("{$package}/tests/Panel/PanelTest.php", HEARTBEAT_PANEL_TEST);

    // Pest roots itself at the checkout its binary sits in, so the package's tests folder is
    // given relative to that, which makes Pest read the package's own tests/Pest.php.
    $tests = str_repeat('../', count(explode('/', trim((string) realpath($repo), '/')))).ltrim((string) realpath("{$package}/tests"), '/');

    // A suite that finds no tests also exits 0, and Pest prints nothing under the prepended
    // autoloader, so each run also writes a JUnit log that counts the tests it passed.
    foreach (['Panel' => 1, 'Feature' => 3] as $suite => $count) {
        $log = "{$base}/{$suite}.xml";
        $pest = new Process([PHP_BINARY, '-d', "auto_prepend_file={$base}/bootstrap.php", "{$repo}/vendor/bin/pest", '--bootstrap', "{$base}/bootstrap.php", "--test-directory={$tests}", '--log-junit', $log, "tests/{$suite}"], $package, timeout: $deadline - time());
        $pest->run();

        expect($pest->getExitCode())->toBe(0, "{$suite}:\n".$pest->getErrorOutput().(is_file($log) ? File::get($log) : ''))
            ->and(File::get($log))->toMatch("/tests=\"{$count}\" assertions=\"\\d+\" errors=\"0\" failures=\"0\"/");
    }
});
