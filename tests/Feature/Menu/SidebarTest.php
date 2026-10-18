<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Gate;
use Lathe\Dashboards\Dashboard;
use Lathe\Enums\Icon;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Menu\Menu;
use Lathe\Menu\MenuItem;
use Lathe\Menu\MenuSection;
use Lathe\Plugins\Plugin;
use Lathe\Tests\Fixtures\Dashboards\Revenue;
use Lathe\Tests\Fixtures\Models\Article as ArticleModel;
use Lathe\Tests\Fixtures\Resources\Article;
use Workbench\App\Models\User;
use Workbench\App\Policies\ActionEventPolicy;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
});

/**
 * The navigation and badge props of one page view.
 *
 * @return array{navigation: list<array<string, mixed>>, navigationBadges: array<string, mixed>}
 */
function sidebarProps(string $email = 'admin@lathephp.com', string $url = '/lathe'): array
{
    $props = test()->actingAs(User::where('email', $email)->firstOrFail())->get($url)->assertOk()->viewData('page')['props'];

    return ['navigation' => $props['navigation'], 'navigationBadges' => $props['navigationBadges']];
}

/**
 * Every href in the navigation, sections and groups included.
 *
 * @param  list<array<string, mixed>>  $entries
 * @return list<string>
 */
function sidebarHrefs(array $entries): array
{
    $hrefs = [];

    foreach ($entries as $entry) {
        array_push($hrefs, ...(isset($entry['items']) ? sidebarHrefs($entry['items']) : [$entry['href']]));
    }

    return $hrefs;
}

class SidebarTestReports extends Dashboard
{
    public function widgets(LatheRequest $request): array
    {
        return [];
    }
}

class SidebarTestReporting extends Plugin
{
    public function boot(): void
    {
        Lathe::registerDashboards(SidebarTestReports::class);
    }

    public function menu(Request $request): ?array
    {
        return [MenuSection::section('Reporting', [MenuItem::dashboard('sidebar-test-reports')])];
    }
}

class SidebarTestArticle extends Article
{
    public static function menu(LatheRequest $request): ?MenuItem
    {
        return MenuItem::link('Drafts', 'resources/sidebar-test-articles?status=draft')->icon(Icon::FileText);
    }
}

it('runs a lazy badge once within its TTL, per user', function () {
    $runs = 0;
    // The callback takes $runs by reference: an arrow function would copy it.
    Menu::using(function (Request $request, Menu $menu) use (&$runs) {
        $menu->add(MenuItem::link('Orders', 'resources/orders')->withBadge(function () use (&$runs) {
            $runs++;

            return 7;
        }, type: 'warning'));
    });

    $this->freezeTime();

    expect(sidebarProps()['navigationBadges']['/lathe/resources/orders'])->toBe(['value' => '7', 'type' => 'warning']);

    $this->travel(59)->seconds();
    sidebarProps();
    expect($runs)->toBe(1);

    $this->travel(2)->seconds();
    sidebarProps();
    expect($runs)->toBe(2);

    sidebarProps('editor@lathephp.com');
    expect($runs)->toBe(3);
});

it('reports a lazy badge that throws and shows the page without it', function () {
    Exceptions::fake();
    Menu::using(fn (Request $request, Menu $menu) => $menu->add(
        MenuItem::link('Orders', 'resources/orders')->withBadge(fn () => throw new RuntimeException('badge failed')),
    ));

    expect(sidebarProps()['navigationBadges'])->not->toHaveKey('/lathe/resources/orders');
    Exceptions::assertReported(RuntimeException::class);
});

it('runs a lazy badge with a TTL of 0 on every view', function () {
    $runs = 0;
    Menu::using(function (Request $request, Menu $menu) use (&$runs) {
        $menu->add(MenuItem::link('Orders', 'resources/orders')->withBadge(function () use (&$runs) {
            return ++$runs;
        }, 0));
    });

    sidebarProps();

    expect(sidebarProps()['navigationBadges']['/lathe/resources/orders'])->toBe(['value' => '2', 'type' => 'info'])
        ->and($runs)->toBe(2);
});

it('keeps lazy badge values out of the navigation JSON', function () {
    Menu::using(fn (Request $request, Menu $menu) => $menu->add(
        MenuItem::link('Orders', 'resources/orders')->withBadge(fn () => 3),
    ));

    expect(sidebarProps()['navigation'][1]['items'])->toBe([['label' => 'Orders', 'href' => '/lathe/resources/orders', 'icon' => null, 'component' => 'Resources/Index', 'cacheTag' => 'resource:orders', 'skeleton' => ['tabs' => true, 'actions' => true]]]);
});

it('sends the active override of activeWhen, activeUnless and active', function () {
    Menu::using(fn (Request $request, Menu $menu) => $menu->add(
        MenuItem::link('When', 'w')->activeWhen(fn (Request $request) => $request->is('lathe/resources/*')),
        MenuItem::link('Unless', 'u')->activeUnless(fn (Request $request) => $request->is('lathe/resources/*')),
        MenuItem::link('Always', 'a')->active(),
    ));

    expect(array_column(sidebarProps(url: '/lathe/resources/orders')['navigation'][1]['items'], 'active', 'label'))
        ->toBe(['When' => true, 'Unless' => false, 'Always' => true]);
});

it('lists a dashboard a plugin registers and links once', function () {
    Lathe::registerPlugins(SidebarTestReporting::make());

    expect(array_count_values(sidebarHrefs(sidebarProps()['navigation']))['/lathe/dashboards/sidebar-test-reports'])->toBe(1)
        ->and(collect(sidebarProps()['navigation'])->firstWhere('label', 'Reporting')['items'])
        ->toBe([['label' => 'Sidebar Test Reports', 'href' => '/lathe/dashboards/sidebar-test-reports', 'icon' => 'LayoutDashboard', 'component' => 'Dashboard']]);
});

it('keeps an explicit menu item where the app put it and drops the automatic entry', function () {
    Lathe::registerDashboards(Revenue::class);
    Menu::using(fn (Request $request, Menu $menu) => $menu
        ->add(MenuSection::section('Sales', [MenuItem::resource('orders'), MenuItem::dashboard('sales')])));

    $navigation = sidebarProps()['navigation'];

    expect(array_count_values(sidebarHrefs($navigation)))->toMatchArray(['/lathe/resources/orders' => 1, '/lathe/dashboards/sales' => 1])
        ->and(sidebarHrefs($navigation[0]['items']))->toBe(['/lathe'])
        ->and(sidebarHrefs($navigation[2]['items']))->toBe(['/lathe/resources/orders', '/lathe/dashboards/sales']);
});

it('replaces a Resource default entry with its own menu() item', function () {
    Gate::policy(ArticleModel::class, ActionEventPolicy::class);
    Lathe::registerResources(SidebarTestArticle::class);

    expect(collect(sidebarProps()['navigation'])->firstWhere('label', 'Content')['items'])
        ->toBe([['label' => 'Drafts', 'href' => '/lathe/resources/sidebar-test-articles?status=draft', 'icon' => 'FileText', 'component' => 'Resources/Index', 'cacheTag' => 'resource:sidebar-test-articles', 'skeleton' => ['tabs' => false, 'actions' => false]]]);
});
