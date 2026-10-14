<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Lathe\Exceptions\PluginDependencyException;
use Lathe\Lathe;
use Lathe\Menu\MenuItem;
use Lathe\Menu\MenuSection;
use Lathe\Plugins\Plugin;
use Lathe\Tests\Fixtures\Plugins\Beacon;
use Lathe\Tests\Fixtures\Plugins\BillingPlugin;
use Lathe\Tests\Fixtures\Plugins\Toolbox;
use Lathe\Tests\Fixtures\Resources\Gadget;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Beacon::$calls = [];
});

function lifecycleAdmin(): User
{
    return User::where('email', 'admin@lathephp.com')->firstOrFail();
}

it('runs register and boot once and serving once per panel request', function () {
    Lathe::registerPlugins(Beacon::make());
    reloadLatheRoutes();
    $this->actingAs(lifecycleAdmin());

    $this->get('/lathe')->assertOk();
    $this->get('/lathe/resources/orders')->assertOk();
    $this->getJson('/lathe/api/plugins/beacon/status')->assertOk();

    expect(array_count_values(Beacon::$calls))->toBe(['register' => 1, 'boot' => 1, 'serving' => 3]);
});

it('answers 403 for a plugin the user may not see and 404 for an unknown one, page and API alike', function () {
    Lathe::registerPlugins(Beacon::make()->canSee(fn () => false));
    reloadLatheRoutes();
    $this->actingAs(lifecycleAdmin());

    $this->getJson('/lathe/api/plugins/beacon/x')->assertForbidden();
    $this->get('/lathe/plugins/beacon')->assertForbidden();
    $this->getJson('/lathe/api/plugins/nobody/x')->assertNotFound();
    $this->get('/lathe/plugins/nobody')->assertNotFound();

    expect(Beacon::$calls)->not->toContain('serving');
});

it('registers its resources() on boot and its middleware() into the lathe group', function () {
    Lathe::registerPlugins(Toolbox::make());
    reloadLatheRoutes();

    expect(Lathe::resources()->forKey(Gadget::uriKey()))->toBe(Gadget::class);

    $this->actingAs(lifecycleAdmin())->get('/lathe')->assertOk()->assertHeader('X-Toolbox', 'on');
});

it('names the plugin and both missing classes when requires() fails', function () {
    expect(fn () => Lathe::registerPlugins(BillingPlugin::make()))->toThrow(
        PluginDependencyException::class,
        'Billing ('.BillingPlugin::class.') requires Acme\\Missing\\Ledger, Acme\\Missing\\Tax',
    );
});

it('keeps a hand-registered configured instance over the discovered one', function () {
    Lathe::registerPlugins(Toolbox::make()->flavour('mint'));
    Lathe::registerPlugins(Toolbox::make());

    expect(array_values(array_filter(Lathe::plugins()->all(), fn ($p) => $p instanceof Toolbox)))->toHaveCount(1)
        ->and(Lathe::plugins()->find('toolbox')->flavour)->toBe('mint');
});

it('builds a Settings link under a section named for the plugin when menu() is null', function () {
    Lathe::registerPlugins(Toolbox::make());
    $this->actingAs(lifecycleAdmin());

    expect(array_map(fn (MenuSection $s) => $s->jsonSerialize(), Lathe::plugins()->menus(request())))->toContain(
        ['label' => 'Toolbox', 'icon' => null, 'items' => [['label' => 'Settings', 'href' => '/lathe/plugins/toolbox/settings', 'icon' => null]]],
    );
});

it('builds no menu for a plugin without settings, none for [] and the array verbatim', function () {
    $bare = Toolbox::make();
    $bare->withSettings = false;
    $headless = new class extends Plugin
    {
        public static function uriKey(): string
        {
            return 'headless';
        }

        public function menu(Request $request): ?array
        {
            return [];
        }
    };
    $own = new class extends Plugin
    {
        public static function uriKey(): string
        {
            return 'own';
        }

        public function menu(Request $request): ?array
        {
            return [MenuSection::section('Own', [MenuItem::link('Here', 'here')])];
        }
    };
    Lathe::registerPlugins($bare, $headless, $own);
    $this->actingAs(lifecycleAdmin());

    $labels = array_map(fn (MenuSection $s) => $s->jsonSerialize()['label'], Lathe::plugins()->menus(request()));

    expect($labels)->not->toContain('Toolbox', 'Headless')->toContain('Own');
});

it('lists the registered plugins and, verbose, their resources and menu', function () {
    Lathe::registerPlugins(Toolbox::make());

    expect(Artisan::call('lathe:plugins'))->toBe(0);
    $output = Artisan::output();
    expect($output)->toContain('Heartbeat', 'Acme\\Heartbeat\\Heartbeat', 'Toolbox', Toolbox::class, 'ok');

    expect(Artisan::call('lathe:plugins', ['-v' => true]))->toBe(0);
    expect(Artisan::output())->toContain(Gadget::class, 'Settings');
});

it('diagnoses a plugin whose requirements are missing and exits 1', function () {
    $broken = new class extends Plugin
    {
        public static function uriKey(): string
        {
            return 'needy';
        }
    };
    Lathe::registerPlugins($broken);
    expect(Artisan::call('lathe:plugins', ['--diagnose' => true]))->toBe(0);

    $needy = new class extends Plugin
    {
        public static bool $armed = false;

        public static function uriKey(): string
        {
            return 'armed';
        }

        public function requires(): array
        {
            return self::$armed ? ['Acme\\Gone\\Thing'] : [];
        }
    };
    Lathe::registerPlugins($needy);
    $needy::$armed = true;

    expect(Artisan::call('lathe:plugins', ['--diagnose' => true]))->toBe(1)
        ->and(Artisan::output())->toContain('Acme\\Gone\\Thing');
});
