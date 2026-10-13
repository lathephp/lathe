<?php

use Illuminate\Support\Facades\Gate;
use Lathe\Exceptions\LatheException;
use Lathe\Exceptions\PluginDependencyException;
use Lathe\Lathe;
use Lathe\Plugins\PluginDiscovery;
use Lathe\Tests\Fixtures\Plugins\Beacon;
use Lathe\Tests\Fixtures\Plugins\BillingPlugin;
use Workbench\App\Models\User;

beforeEach(function () {
    Beacon::$calls = [];
    Gate::define('viewLathe', fn () => true);
});

function signIn(): void
{
    test()->actingAs((new User)->forceFill(['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.test']));
}

function registerBeacon(?Beacon $plugin = null): Beacon
{
    $plugin ??= Beacon::make();
    Lathe::registerPlugins($plugin);
    reloadLatheRoutes();

    return $plugin;
}

it('derives the key from the class name', function () {
    expect(Beacon::uriKey())->toBe('beacon')
        ->and(BillingPlugin::uriKey())->toBe('billing')
        ->and(Beacon::make()->name())->toBe('Beacon');
});

it('runs register and boot once, and a repeat registration is a no-op', function () {
    $first = Beacon::make();
    Lathe::registerPlugins($first);
    Lathe::registerPlugins(Beacon::make());

    expect(Beacon::$calls)->toBe(['register', 'boot'])
        ->and(Lathe::plugins()->find('beacon'))->toBe($first)
        ->and(array_filter(Lathe::plugins()->all(), fn ($plugin) => $plugin instanceof Beacon))->toHaveCount(1);
});

it('names the plugin and every missing class it requires', function () {
    Lathe::registerPlugins(BillingPlugin::make());
})->throws(PluginDependencyException::class, BillingPlugin::class.' requires Acme\\Missing\\Ledger, Acme\\Missing\\Tax');

it('fails when two plugins share a key', function () {
    $other = new class extends Beacon
    {
        public static function uriKey(): string
        {
            return 'beacon';
        }
    };
    Lathe::registerPlugins(Beacon::make(), $other::make());
})->throws(LatheException::class, 'beacon');

it('registers a package that declares a plugin in its composer.json', function () {
    $vendor = sys_get_temp_dir().'/lathe-vendor-'.bin2hex(random_bytes(4));
    mkdir("{$vendor}/composer", recursive: true);
    file_put_contents("{$vendor}/composer/installed.json", json_encode(['packages' => [
        ['name' => 'acme/beacon', 'extra' => ['lathe' => ['plugin' => Beacon::class]]],
        ['name' => 'acme/other', 'extra' => []],
    ]]));

    (new PluginDiscovery($vendor))->register(Lathe::plugins());

    expect(Lathe::plugins()->find('beacon'))->toBeInstanceOf(Beacon::class);
});

it('runs serving on each panel request for authorized plugins only', function () {
    registerBeacon();
    signIn();

    $this->getJson('/lathe/api/plugins/beacon/status')->assertOk();
    $this->getJson('/lathe/api/plugins/beacon/status')->assertOk();
    expect(array_count_values(Beacon::$calls)['serving'])->toBe(2);

    Beacon::$calls = [];
    $this->get('/host-nothing');
    expect(Beacon::$calls)->toBe([]);
});

it('skips serving and answers 403 when authorize refuses', function () {
    registerBeacon(Beacon::make()->canSee(fn () => false));
    signIn();

    $this->getJson('/lathe/api/plugins/beacon/status')->assertForbidden();
    expect(Beacon::$calls)->not->toContain('serving');
});

it('routes the plugin API, with package routes beating the catch-all', function () {
    registerBeacon();
    signIn();

    $this->getJson('/lathe/api/plugins/beacon/status')->assertOk()->assertExactJson(['status' => 'ok']);
    $this->getJson('/lathe/api/plugins/beacon/ping')->assertOk()->assertExactJson(['pong' => true]);
    $this->postJson('/lathe/api/plugins/beacon/status')->assertNotFound();
    $this->getJson('/lathe/api/plugins/beacon/nothing')->assertNotFound();
    $this->getJson('/lathe/api/plugins/nobody/status')->assertNotFound();
});

it('serves a registered module by its content hash, and nothing else', function () {
    registerBeacon();
    $hash = substr(hash_file('sha256', dirname(__DIR__, 2).'/Fixtures/assets/beacon.js') ?: '', 0, 8);

    $this->get("/lathe/modules/beacon-{$hash}.js")
        ->assertOk()
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public')
        ->assertHeader('Content-Type', 'text/javascript; charset=utf-8')
        ->assertCookieMissing('laravel_session');

    expect(Lathe::plugins()->moduleUrls())->toContain("/lathe/modules/beacon-{$hash}.js");

    $this->get('/lathe/modules/beacon-00000000.js')->assertNotFound();
    $this->get("/lathe/modules/other-{$hash}.js")->assertNotFound();
});

it('renders the plugin page for an authorized plugin', function () {
    registerBeacon();
    signIn();

    $this->get('/lathe/plugins/beacon')->assertOk();
    $this->get('/lathe/plugins/nobody')->assertNotFound();
});

it('still lists a plugin script whose file is missing, so the panel reports it', function () {
    $missing = new class extends Beacon
    {
        public static function uriKey(): string
        {
            return 'ghost';
        }

        public function assets(): array
        {
            return ['scripts' => ['ghost' => '/nowhere/ghost.js']];
        }
    };
    Lathe::registerPlugins($missing);

    expect(Lathe::plugins()->moduleUrls())->toContain('/lathe/modules/ghost-00000000.js');
    $this->get('/lathe/modules/ghost-00000000.js')->assertNotFound();
});
