<?php

use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Lathe\LatheServiceProvider;
use Lathe\Tests\Feature\Console\Generators\GeneratesIntoTempApp;

uses(GeneratesIntoTempApp::class);

const WRITABLE = ['app/', 'bootstrap/', 'config/', 'stubs/lathe/', 'lang/vendor/lathe/', 'public/vendor/lathe/', 'database/migrations/'];
// vite.lathe.config.ts sits beside the host's own vite.config.ts, which the generators never touch.
const GENERATOR_WRITABLE = ['lathe-components/', 'resources/js/lathe/', 'composer.json', 'package.json', 'pnpm-workspace.yaml', 'vite.lathe.config.ts'];

/**
 * Every file under a folder with a hash of its contents, keyed by its path below the folder.
 *
 * @return array<string, string>
 */
function snapshot(string $base): array
{
    $files = [];

    foreach (File::allFiles($base, true) as $file) {
        $files[$file->getRelativePathname()] = md5_file($file->getPathname());
    }

    return $files;
}

/**
 * The paths that were added, changed or removed between two snapshots.
 *
 * @param  array<string, string>  $before
 * @param  array<string, string>  $after
 * @return list<string>
 */
function changedPaths(array $before, array $after): array
{
    return array_values(array_unique([
        ...array_keys(array_diff_assoc($after, $before)),
        ...array_keys(array_diff_key($before, $after)),
    ]));
}

/** A temp app whose config, public, storage, bootstrap, lang and database paths all sit inside its base path. */
function isolatedApp(): string
{
    $test = test();
    $test->artisan('migrate')->assertSuccessful();

    $skeleton = base_path();
    $base = $test->generatorApp();

    foreach (['config', 'public', 'storage', 'bootstrap/cache', 'lang', 'database/migrations', 'resources/js'] as $folder) {
        File::ensureDirectoryExists("{$base}/{$folder}");
    }

    File::put("{$base}/bootstrap/providers.php", "<?php\n\nreturn [\n];\n");

    app()->instance('lathe.host_path', $base);
    app()->useConfigPath("{$base}/config");
    app()->usePublicPath("{$base}/public");
    app()->useStoragePath("{$base}/storage");
    app()->useBootstrapPath("{$base}/bootstrap");
    app()->useLangPath("{$base}/lang");
    app()->useDatabasePath("{$base}/database");

    // Publish targets were fixed at boot under the shared Testbench skeleton, where a published
    // config/lathe.php would shadow the package's for every later test; point them in here.
    $into = fn (array $paths): array => array_map(fn (string $target) => str_replace($skeleton, $base, $target), $paths);
    ServiceProvider::$publishes = array_map($into, ServiceProvider::$publishes);
    ServiceProvider::$publishGroups = array_map($into, ServiceProvider::$publishGroups);

    return $base;
}

it('exits 1 and names the missing input under --no-interaction', function (string $command, string $missing) {
    $this->artisan($command, ['--no-interaction' => true])
        ->expectsOutputToContain($missing)
        ->assertExitCode(1);
})->with([
    'resource' => ['lathe:resource', 'name'],
    'action' => ['lathe:action', 'name'],
    'filter' => ['lathe:filter', 'name'],
    'lens' => ['lathe:lens', 'name'],
    'widget' => ['lathe:widget', 'name'],
    'field' => ['lathe:field', 'name'],
    'dashboard' => ['lathe:dashboard', 'name'],
    'page' => ['lathe:page', 'name'],
    'plugin' => ['lathe:plugin', 'name'],
    'resource-plugin' => ['lathe:resource-plugin', 'name'],
    'translate' => ['lathe:translate', 'language'],
    'user' => ['lathe:user', '--name, --email, --password'],
]);

it('changes only the folders it may write, makes no request', function (string $command, array $arguments, bool $generator, ?int $exit = 0) {
    Http::preventStrayRequests();
    $base = isolatedApp();
    File::put("{$base}/package.json", '{}');

    $before = snapshot($base);

    $run = $this->artisan($command, [...$arguments, '--no-interaction' => true]);

    if ($exit !== null) {
        $run->assertExitCode($exit);
    } else {
        $run->run();
    }

    $allowed = $generator ? [...WRITABLE, ...GENERATOR_WRITABLE] : WRITABLE;
    $outside = array_filter(
        changedPaths($before, snapshot($base)),
        fn (string $path) => ! collect($allowed)->contains(fn (string $prefix) => str_starts_with($path, $prefix)),
    );

    expect(array_values($outside))->toBe([]);
})->with([
    'install' => ['lathe:install', [], false],
    'uninstall' => ['lathe:uninstall', [], false],
    'user' => ['lathe:user', ['--name' => 'Grace Hopper', '--email' => 'grace@example.test', '--password' => 'secret-pass'], false],
    // Doctor's exit code follows the checkout (a missing build is a FAIL), so only its writes count.
    'doctor' => ['lathe:doctor', [], false, null],
    'cache' => ['lathe:cache', [], false],
    'clear' => ['lathe:clear', [], false],
    'prune' => ['lathe:prune', [], false],
    'prune-action-events' => ['lathe:prune-action-events', [], false],
    'assets' => ['lathe:assets', [], false],
    'stubs' => ['lathe:stubs', [], false],
    'translate' => ['lathe:translate', ['language' => 'fr'], false],
    'plugins' => ['lathe:plugins', [], false],
    'resource' => ['lathe:resource', ['name' => 'Order'], true],
    'action' => ['lathe:action', ['name' => 'Archive'], true],
    'filter' => ['lathe:filter', ['name' => 'Status'], true],
    'lens' => ['lathe:lens', ['name' => 'Recent'], true],
    'widget' => ['lathe:widget', ['name' => 'Revenue'], true],
    'field' => ['lathe:field', ['name' => 'Rating', '--custom' => true], true],
    'dashboard' => ['lathe:dashboard', ['name' => 'Sales'], true],
    'page' => ['lathe:page', ['name' => 'Reports'], true],
    'inline plugin' => ['lathe:plugin', ['name' => 'Tracker', '--inline' => true], true],
    'package plugin' => ['lathe:plugin', ['name' => 'acme/tracker', '--skip-composer' => true], true],
    'resource-plugin' => ['lathe:resource-plugin', ['name' => 'Gallery'], true],
]);

it('makes a request only from lathe:preset', function () {
    Http::fake();
    $base = isolatedApp();
    File::copy(dirname(__DIR__, 3).'/config/lathe.php', "{$base}/config/lathe.php");

    $this->artisan('lathe:preset', ['preset' => 'a5ONTE', '--no-interaction' => true]);

    Http::assertSentCount(1);
});

it('registers no lathe command outside the console', function () {
    expect(array_keys(Artisan::all()))->toContain('lathe:doctor');

    (new ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);
    ConsoleApplication::forgetBootstrappers();
    $this->app->forgetInstance(Kernel::class);
    $this->app->register(new LatheServiceProvider($this->app), true);

    $lathe = array_filter(array_keys($this->app->make(Kernel::class)->all()), fn (string $name) => str_starts_with($name, 'lathe:'));

    expect(array_values($lathe))->toBe([]);
});

it('publishes the English strings under lang/vendor/lathe', function () {
    $base = isolatedApp();

    $this->artisan('vendor:publish', ['--tag' => 'lathe-translations'])->assertSuccessful();

    expect(File::get("{$base}/lang/vendor/lathe/en.json"))->toBe(File::get(dirname(__DIR__, 3).'/lang/en.json'));
});

it('publishes the User Resource with the App namespace and the auth model', function () {
    $base = isolatedApp();

    $this->artisan('vendor:publish', ['--tag' => 'lathe-resource'])->assertSuccessful();

    $published = File::get("{$base}/app/Lathe/Resources/User.php");

    expect($published)->toContain('namespace App\\Lathe\\Resources;')
        ->and($published)->toContain('use '.config('auth.providers.users.model').' as UserModel;')
        ->and($published)->not->toContain('{{');
});
