<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use Lathe\Lathe;
use Lathe\Tests\Fixtures\Models\Invoice;
use Lathe\Tests\Fixtures\Plugins\Gauge;
use Lathe\Tests\Fixtures\Policies\EmptyPolicy;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
});

function navigationFor(string $email): array
{
    $props = test()->actingAs(User::where('email', $email)->firstOrFail())->get('/lathe')->assertOk()->viewData('page')['props'];

    return $props['navigation'];
}

it('lists the dashboard and the Orders Resource', function () {
    expect(navigationFor('admin@lathephp.com'))->toBe([
        ['label' => null, 'icon' => null, 'items' => [['label' => 'Dashboard', 'href' => '/lathe', 'icon' => 'LayoutDashboard']]],
        ['label' => 'Resources', 'icon' => null, 'items' => [['label' => 'Orders', 'href' => '/lathe/resources/orders', 'icon' => 'ShoppingCart']]],
    ]);
});

it('adds the sections an authorized plugin returns from menu()', function () {
    Lathe::registerPlugins(Gauge::make());

    expect(navigationFor('admin@lathephp.com')[1])->toBe(
        ['label' => 'Gauge', 'icon' => null, 'items' => [['label' => 'Gauge', 'href' => '/lathe/plugins/gauge', 'icon' => 'Activity']]],
    );
});

it('leaves out a Resource the user may not viewAny', function () {
    Gate::policy(Order::class, EmptyPolicy::class);

    expect(collect(navigationFor('admin@lathephp.com'))->pluck('label')->all())->toBe([null]);
});

it('builds one Resource definition for the sidebar and the Orders index among 500 Resources', function () {
    $namespace = 'Nav'.bin2hex(random_bytes(4));
    $directory = sys_get_temp_dir().'/lathe-nav-'.$namespace;
    File::ensureDirectoryExists($directory);
    foreach (range(1, 499) as $i) {
        File::put("{$directory}/Thing{$i}.php", "<?php\nnamespace {$namespace};\nclass Thing{$i} extends \\Lathe\\Resources\\Resource {\n    protected function configure(\\Lathe\\Resources\\ResourceDefinition \$d): \\Lathe\\Resources\\ResourceDefinition { return \$d->model(\\".Invoice::class."::class)->canonical(false); }\n    public function fields(\\Lathe\\Http\\Requests\\LatheRequest \$r): array { return []; }\n}\n");
    }
    Lathe::discoverResources($directory, $namespace);
    $this->artisan('lathe:cache')->assertSuccessful();
    Lathe::resources()->flush();

    $queries = [];
    DB::listen(function ($event) use (&$queries) {
        $queries[] = $event->sql;
    });
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail())
        ->get('/lathe/resources/orders')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('navigation.1.items', 1));

    expect(Lathe::resources()->definitionsBuilt())->toBe(1)
        ->and(collect($queries)->filter(fn ($sql) => str_contains($sql, 'invoices'))->all())->toBe([]);

    Lathe::resources()->clearCache();
    File::deleteDirectory($directory);
});
