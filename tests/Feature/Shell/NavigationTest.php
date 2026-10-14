<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use Lathe\Lathe;
use Lathe\Menu\Menu;
use Lathe\Menu\MenuItem;
use Lathe\Menu\MenuSection;
use Lathe\Tests\Fixtures\Models\Article as ArticleModel;
use Lathe\Tests\Fixtures\Models\Invoice;
use Lathe\Tests\Fixtures\Plugins\Gauge;
use Lathe\Tests\Fixtures\Policies\EmptyPolicy;
use Lathe\Tests\Fixtures\Resources\Article;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\App\Policies\ActionEventPolicy;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
});

class NavigationTestCountingPolicy
{
    public static int $checks = 0;

    public function viewAny(User $user): bool
    {
        static::$checks++;

        return false;
    }
}

/** How often a generated Resource was built or asked for its fields. */
class NavigationTestBuilds
{
    public static int $count = 0;
}

function navigationFor(string $email): array
{
    $props = test()->actingAs(User::where('email', $email)->firstOrFail())->get('/lathe')->assertOk()->viewData('page')['props'];

    return $props['navigation'];
}

it('orders the dashboards, the Navigation group, the sections, the plugin sections and the Resource groups', function () {
    Gate::policy(ArticleModel::class, ActionEventPolicy::class);
    Lathe::registerResources(Article::class);
    Lathe::registerPlugins(Gauge::make());
    Menu::using(fn (Request $request, Menu $menu) => $menu
        ->add(MenuSection::section('Insights', [MenuItem::link('Overview', 'pages/overview')]))
        ->add(MenuItem::link('Docs', '/docs')));

    $navigation = navigationFor('admin@lathephp.com');

    expect(array_column($navigation, 'label'))->toBe(['Dashboards', 'Navigation', 'Insights', 'Insights', 'Hello Plugin', 'Heartbeat', 'Gauge', 'Content', 'Resources'])
        ->and($navigation[1]['items'])->toBe([['label' => 'Docs', 'href' => '/docs', 'icon' => null]])
        ->and($navigation[7]['items'])->toBe([['label' => 'News', 'href' => '/lathe/resources/articles', 'icon' => 'FileText']]);
});

it('lists the dashboard and the Resources', function () {
    expect(navigationFor('admin@lathephp.com'))->toBe([
        ['label' => 'Dashboards', 'icon' => null, 'items' => [['label' => 'Sales', 'href' => '/lathe', 'icon' => 'LayoutDashboard']]],
        ['label' => 'Insights', 'icon' => null, 'items' => [
            ['label' => 'Overview', 'href' => '/lathe/plugins/insights', 'icon' => 'BarChart'],
            ['label' => 'Widget Gallery', 'href' => '/lathe/dashboards/widget-gallery', 'icon' => 'LayoutDashboard'],
        ]],
        ['label' => 'Demo Kit', 'icon' => null, 'items' => [
            ['label' => 'UI Gallery', 'href' => '/lathe/pages/ui-gallery', 'icon' => null],
            ['label' => 'System Status', 'href' => '/lathe/pages/system-status', 'icon' => null],
        ]],
        ['label' => 'Hello Plugin', 'icon' => null, 'items' => [['label' => 'Settings', 'href' => '/lathe/plugins/hello-plugin/settings', 'icon' => null]]],
        ['label' => 'Heartbeat', 'icon' => null, 'items' => [['label' => 'Heartbeat', 'href' => '/lathe/plugins/heartbeat', 'icon' => 'Activity']]],
        ['label' => 'Resources', 'icon' => null, 'items' => [
            ['label' => 'Categories', 'href' => '/lathe/resources/categories', 'icon' => 'Folder'],
            ['label' => 'Comments', 'href' => '/lathe/resources/comments', 'icon' => 'MessageSquare'],
            ['label' => 'Customers', 'href' => '/lathe/resources/customers', 'icon' => 'Users'],
            ['label' => 'Order Items', 'href' => '/lathe/resources/order-items', 'icon' => 'Receipt'],
            ['label' => 'Orders', 'href' => '/lathe/resources/orders', 'icon' => 'ShoppingCart'],
            ['label' => 'Posts', 'href' => '/lathe/resources/posts', 'icon' => null],
            ['label' => 'Products', 'href' => '/lathe/resources/products', 'icon' => 'Package'],
            ['label' => 'Tags', 'href' => '/lathe/resources/tags', 'icon' => 'Tag'],
            ['label' => 'Users', 'href' => '/lathe/resources/users', 'icon' => null],
        ]],
    ]);
});

it('adds the sections an authorized plugin returns from menu()', function () {
    Lathe::registerPlugins(Gauge::make());

    expect(navigationFor('admin@lathephp.com')[5])->toBe(
        ['label' => 'Gauge', 'icon' => null, 'items' => [['label' => 'Gauge', 'href' => '/lathe/plugins/gauge', 'icon' => 'Activity']]],
    );
});

it('leaves out a Resource the user may not viewAny', function () {
    Gate::policy(Order::class, EmptyPolicy::class);

    expect(collect(navigationFor('admin@lathephp.com'))->pluck('items.*.label')->flatten()->all())->not->toContain('Orders');
});

it('builds one Resource definition for the sidebar and the Orders index among 500 Resources', function () {
    $namespace = 'Nav'.bin2hex(random_bytes(4));
    $directory = sys_get_temp_dir().'/lathe-nav-'.$namespace;
    File::ensureDirectoryExists($directory);
    foreach (range(1, 499) as $i) {
        File::put("{$directory}/Thing{$i}.php", "<?php\nnamespace {$namespace};\nclass Thing{$i} extends \\Lathe\\Resources\\Resource {\n    public static function make(?\\Illuminate\\Database\\Eloquent\\Model \$m = null): static { \\NavigationTestBuilds::\$count++; return parent::make(\$m); }\n    protected function configure(\\Lathe\\Resources\\ResourceDefinition \$d): \\Lathe\\Resources\\ResourceDefinition { return \$d->model(\\".Invoice::class."::class)->canonical(false); }\n    public function fields(\\Lathe\\Http\\Requests\\LatheRequest \$r): array { \\NavigationTestBuilds::\$count++; return []; }\n}\n");
    }
    Lathe::discoverResources($directory, $namespace);
    $this->artisan('lathe:cache')->assertSuccessful();
    // A failed expectation must not leave the 499 cached Resources to the tests after this one.
    $this->beforeApplicationDestroyed(function () use ($directory) {
        Lathe::resources()->clearCache();
        File::deleteDirectory($directory);
    });
    Lathe::resources()->flush();
    NavigationTestBuilds::$count = 0;
    Gate::policy(Invoice::class, NavigationTestCountingPolicy::class);

    $queries = [];
    DB::listen(function ($event) use (&$queries) {
        $queries[] = $event->sql;
    });
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());

    // The home dashboard builds no Resource for its sidebar and asks viewAny once per Resource.
    $this->get('/lathe')->assertOk();

    expect(Lathe::resources()->definitionsBuilt())->toBe(0)
        ->and(NavigationTestBuilds::$count)->toBe(0)
        ->and(NavigationTestCountingPolicy::$checks)->toBe(499)
        ->and(collect($queries)->filter(fn ($sql) => str_contains($sql, 'invoices'))->all())->toBe([]);

    Lathe::resources()->flush();
    Lathe::resourcePlugins()->flush();
    $this->get('/lathe/resources/orders')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('navigation.5.items', 9));

    // Orders, and Customers for the titles its Customer column shows; never the 499 others.
    expect(Lathe::resources()->definitionsBuilt())->toBe(2)
        ->and(NavigationTestBuilds::$count)->toBe(0)
        ->and(collect($queries)->filter(fn ($sql) => str_contains($sql, 'invoices'))->all())->toBe([]);
});

it('sends no navigation to a user who fails viewLathe', function () {
    $guest = User::where('email', 'guest@lathephp.com')->firstOrFail();

    $props = $this->actingAs($guest)->get('/lathe')->assertForbidden()->viewData('page')['props'];

    expect($props['navigation'])->toBe([]);
});

it('sends the navigation once per hash of its JSON', function () {
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    $page = $this->get('/lathe')->assertOk()->viewData('page');
    $key = array_key_first(array_filter($page['onceProps'], fn (array $once) => $once['prop'] === 'navigation'));

    expect($key)->toStartWith('navigation:');

    $visit = fn (string $loaded) => $this->get('/lathe', ['X-Inertia' => 'true', 'X-Inertia-Except-Once-Props' => $loaded])->assertOk()->json('props');

    expect($visit($key))->not->toHaveKey('navigation')
        ->and($visit('navigation:stale')['navigation'][0]['label'])->toBe('Dashboards');
});
