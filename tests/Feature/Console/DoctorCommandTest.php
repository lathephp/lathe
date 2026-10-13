<?php

use Illuminate\Foundation\PackageManifest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Lathe\Lathe;
use Lathe\Tests\Fixtures\Plugins\Beacon;
use Lathe\Tests\Fixtures\Plugins\BillingPlugin;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    config(['queue.default' => 'database']);
    Gate::define('viewLathe', fn () => true);

    $this->dist = sys_get_temp_dir().'/lathe-dist-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists("{$this->dist}/assets");
    File::put("{$this->dist}/assets/app-abc12345.js", '');
    File::put("{$this->dist}/manifest.json", json_encode(['resources/js/app.tsx' => ['file' => 'assets/app-abc12345.js']]));
    Lathe::assets()->useDistPath($this->dist);
});

afterEach(function () {
    File::deleteDirectory($this->dist);
    Lathe::resources()->clearCache();
});

/**
 * @return array{0: int, 1: list<string>}
 */
function doctor(): array
{
    $code = Artisan::call('lathe:doctor');

    return [$code, array_values(array_filter(explode("\n", Artisan::output())))];
}

it('prints one line per check in order and a count, and exits 0 on a healthy setup', function () {
    $this->artisan('lathe:cache')->assertSuccessful();

    [$code, $lines] = doctor();

    expect($code)->toBe(0)
        ->and(end($lines))->toMatch('/^\d+ OK, 0 WARN, 0 FAIL$/')
        ->and(array_slice($lines, 0, -1))->each->toMatch('/^(OK|WARN|FAIL|SKIP) +\S/');

    $order = array_map(fn (string $needle) => collect($lines)->search(fn ($line) => str_contains($line, $needle)), [
        'PHP ', 'config/lathe.php', 'Table lathe_bulk_runs', 'Resources', 'No plugins', 'Built assets',
    ]);
    expect($order)->toBe(collect($order)->sort()->values()->all());
});

it('fails once with the build hint when the manifest is gone', function () {
    File::move("{$this->dist}/manifest.json", "{$this->dist}/manifest.old");

    [$code, $lines] = doctor();
    $fails = array_values(array_filter($lines, fn ($line) => str_starts_with($line, 'FAIL')));

    expect($code)->toBe(1)
        ->and($fails)->toHaveCount(1)
        ->and($fails[0])->toContain('run pnpm build');
});

it('prints a FAIL for each bad config value', function () {
    config(['lathe.table.density' => 'roomy', 'lathe.table.striped' => 'yes']);

    [$code, $lines] = doctor();

    expect($code)->toBe(1)
        ->and(array_filter($lines, fn ($line) => str_starts_with($line, 'FAIL config/lathe.php')))->toHaveCount(2);
});

it('warns about an unknown config key and still exits 0', function () {
    $this->artisan('lathe:cache');
    config(['lathe.colour' => 'blue']);

    [$code, $lines] = doctor();

    expect($code)->toBe(0)
        ->and($lines)->toContain('WARN config/lathe.php: unknown key colour. Remove it or check its spelling.');
});

it('fails when the viewLathe gate is undefined', function () {
    forgetGate('viewLathe');

    [$code, $lines] = doctor();

    expect($code)->toBe(1)
        ->and(implode("\n", $lines))->toContain('FAIL The viewLathe gate is not defined');
});

it('skips every table check when the database is unreachable', function () {
    config(['database.connections.broken' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'x', 'username' => 'x', 'password' => 'x']]);
    config(['database.default' => 'broken']);

    [$code, $lines] = doctor();

    expect($code)->toBe(1)
        ->and(array_filter($lines, fn ($line) => str_starts_with($line, 'SKIP Table ')))->toHaveCount(10);
});

it('fails on a plugin requirement, a missing range and a range that misses the installed version', function () {
    $vendor = sys_get_temp_dir().'/lathe-vendor-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists("{$vendor}/composer");
    File::put("{$vendor}/composer/installed.json", json_encode(['packages' => [
        ['name' => 'acme/beacon', 'require' => ['lathe/lathe' => '^9.0'], 'extra' => ['lathe' => ['plugin' => Beacon::class]]],
    ]]));
    app(PackageManifest::class)->vendorPath = $vendor;
    Lathe::registerPlugins(Beacon::make());

    [, $lines] = doctor();
    expect(implode("\n", $lines))->toContain('FAIL Plugin '.Beacon::class.' (acme/beacon) needs lathe/lathe ^9.0');

    File::put("{$vendor}/composer/installed.json", json_encode(['packages' => [
        ['name' => 'acme/beacon', 'require' => [], 'extra' => ['lathe' => ['plugin' => Beacon::class]]],
    ]]));
    [, $lines] = doctor();
    expect(implode("\n", $lines))->toContain('declares no lathe/lathe range');

    $plugins = Lathe::plugins();
    (fn () => $this->plugins['billing'] = BillingPlugin::make())->call($plugins);
    [$code, $lines] = doctor();
    expect($code)->toBe(1)
        ->and(implode("\n", $lines))->toContain('FAIL Plugin '.BillingPlugin::class.' requires Acme\\Missing\\Ledger');
});

it('writes no file and makes no network call', function () {
    Http::preventStrayRequests();
    $before = snapshotTree([base_path(), $this->dist]);

    doctor();

    expect(snapshotTree([base_path(), $this->dist]))->toBe($before);
});

/**
 * Every file under the given folders with its size and modification time.
 *
 * @param  list<string>  $folders
 * @return array<string, string>
 */
function snapshotTree(array $folders): array
{
    $files = [];
    foreach ($folders as $folder) {
        foreach (File::allFiles($folder) as $file) {
            // Parallel test processes write sessions and logs under the shared skeleton's storage.
            if (str_starts_with($file->getPathname(), base_path('storage'))) {
                continue;
            }

            $files[$file->getPathname()] = $file->getSize().':'.$file->getMTime();
        }
    }
    ksort($files);

    return $files;
}
