<?php

use Illuminate\Foundation\PackageManifest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Lathe\Lathe;
use Lathe\Plugins\PluginDiscovery;
use Symfony\Component\Process\Process;
use Workbench\App\Models\User;

beforeEach(function () {
    holdBuildLock();
    Gate::define('viewLathe', fn () => true);
    $this->actingAs((new User)->forceFill(['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.test']));
});

function heartbeatFolder(): string
{
    return dirname(__DIR__, 3).'/workbench/lathe-components/Heartbeat';
}

/**
 * The built Heartbeat module. Builds it once when dist is missing, which takes about 2 seconds.
 */
function heartbeatModule(): string
{
    $file = heartbeatFolder().'/dist/heartbeat.js';

    if (! is_file($file)) {
        (new Process(['pnpm', '--filter', '@lathephp/vite-plugin', 'build'], dirname(heartbeatFolder(), 3), timeout: 300))->mustRun();
        (new Process(['pnpm', 'build'], heartbeatFolder(), timeout: 300))->mustRun();
    }

    return $file;
}

it('registers from its composer.json with no provider code', function () {
    $vendor = PluginDiscovery::vendorPath(app(PackageManifest::class)->vendorPath);

    expect((new PluginDiscovery($vendor))->classes())->toContain('Acme\Heartbeat\Heartbeat')
        ->and(get_class(Lathe::plugins()->find('heartbeat') ?? new stdClass))->toBe('Acme\Heartbeat\Heartbeat');
});

it('answers the status with app, php, laravel, database and time', function () {
    $json = $this->getJson('/lathe/api/plugins/heartbeat/status')->assertOk()->json();

    expect(array_keys($json))->toBe(['app', 'php', 'laravel', 'database', 'time'])
        ->and($json['app'])->toBe('Laravel')
        ->and($json['php'])->toBe(PHP_VERSION)
        ->and($json['laravel'])->toBe(app()->version())
        ->and($json['database'])->toBe('connected')
        ->and($json['time'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+Z$/');
});

it('reports the database as down when it cannot connect', function () {
    config(['database.connections.stopped' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'x', 'username' => 'x', 'password' => 'x']]);
    config(['database.default' => 'stopped']);

    $this->getJson('/lathe/api/plugins/heartbeat/status')->assertOk()->assertJsonPath('database', 'down');
});

it('answers ping from its package route, and 404 for any other verb or path', function () {
    $this->getJson('/lathe/api/plugins/heartbeat/ping')->assertOk()->assertExactJson(['pong' => true]);
    $this->postJson('/lathe/api/plugins/heartbeat/status')->assertNotFound();
    $this->getJson('/lathe/api/plugins/heartbeat/nothing')->assertNotFound();
});

it('serves its built module by content hash, and nothing else', function () {
    $hash = substr((string) hash_file('sha256', heartbeatModule()), 0, 8);

    $this->get("/lathe/modules/heartbeat-{$hash}.js")
        ->assertOk()
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');

    $this->get('/lathe/modules/heartbeat-00000000.js')->assertNotFound();
    $this->get("/lathe/modules/heartbeet-{$hash}.js")->assertNotFound();
})->group('frontend');

it('adds a Heartbeat section to the sidebar with the Activity icon', function () {
    $this->artisan('migrate')->assertSuccessful();
    $admin = User::forceCreate(['name' => 'Admin User', 'email' => 'admin@lathephp.com', 'password' => bcrypt('password')]);

    $navigation = $this->actingAs($admin)->get('/lathe')->assertOk()->viewData('page')['props']['navigation'];

    expect($navigation)->toContain(['label' => 'Heartbeat', 'icon' => null, 'items' => [
        ['label' => 'Heartbeat', 'href' => '/lathe/plugins/heartbeat', 'icon' => 'Activity'],
    ]]);
});

it('pins that the Heartbeat page imports only React and the two public Lathe packages', function () {
    $source = File::get(dirname(__DIR__, 3).'/workbench/lathe-components/Heartbeat/resources/js/plugin.tsx');
    preg_match_all("/^import .* from '([^']+)';$/m", $source, $imports);

    expect(array_values(array_unique($imports[1])))->toEqualCanonicalizing(['@lathephp/core', '@lathephp/ui', 'react']);
});
