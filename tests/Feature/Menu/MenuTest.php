<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lathe\Enums\Icon;
use Lathe\Menu\Badge;
use Lathe\Menu\Menu;
use Lathe\Menu\MenuGroup;
use Lathe\Menu\MenuItem;
use Lathe\Menu\MenuSection;

/**
 * The labels the main menu builds for a request, top level only.
 *
 * @return list<string|null>
 */
function builtLabels(?Request $request = null): array
{
    return array_column(Menu::build($request ?? Request::create('/'))->jsonSerialize(), 'label');
}

it('prepends to the start and adds to the end', function () {
    Menu::using(fn (Request $request, Menu $menu) => $menu
        ->add(MenuItem::link('A', 'a'))
        ->append(MenuItem::link('B', 'b'))
        ->prepend(MenuItem::link('C', 'c')));

    expect(array_values(array_diff(builtLabels(), ['Heartbeat'])))->toBe(['C', 'A', 'B']);
});

it('registers the same callback through using and mainMenu', function () {
    Menu::mainMenu(fn (Request $request, Menu $menu) => $menu->add(MenuItem::link('Via main', 'x')));

    expect(builtLabels())->toContain('Via main');
});

it('hands the callback the request', function () {
    $seen = null;
    Menu::using(function (Request $request, Menu $menu) use (&$seen) {
        $seen = $request->query('probe');
    });

    Menu::build(Request::create('/?probe=1'));

    expect($seen)->toBe('1');
});

it('starts with the plugin menus and keeps the user menu apart', function () {
    Menu::using(fn (Request $request, Menu $menu) => $menu->add(MenuItem::link('Own', 'own')));
    Menu::userMenu(fn (Request $request, Menu $menu) => $menu->add(MenuItem::link('Profile', 'profile')));
    $request = Request::create('/');

    expect(builtLabels($request))->toBe(['Heartbeat', 'Own'])
        ->and(array_column(Menu::buildUserMenu($request)->jsonSerialize(), 'label'))->toBe(['Profile']);
});

it('resolves item targets under the panel path', function (Closure $make, string $href) {
    expect($make()->jsonSerialize()['href'])->toBe($href);
})->with([
    'link with an absolute path' => [fn () => MenuItem::link('Docs', '/lathe/pages/ui-gallery'), '/lathe/pages/ui-gallery'],
    'link with a panel path' => [fn () => MenuItem::link('Docs', 'pages/ui-gallery'), '/lathe/pages/ui-gallery'],
    'resource' => [fn () => MenuItem::resource('orders'), '/lathe/resources/orders'],
    'dashboard' => [fn () => MenuItem::dashboard('widget-gallery'), '/lathe/dashboards/widget-gallery'],
    'plugin sub path' => [fn () => MenuItem::plugin('Acme\Heartbeat\Heartbeat', 'status'), '/lathe/plugins/heartbeat/status'],
    'lens' => [fn () => MenuItem::lens('orders', 'pending-orders'), '/lathe/resources/orders/lens/pending-orders'],
    'external link' => [fn () => MenuItem::externalLink('Docs', 'https://lathephp.com'), 'https://lathephp.com'],
]);

it('names a lens after its label unless told otherwise', function () {
    expect(MenuItem::lens('orders', 'pending-orders')->jsonSerialize()['label'])->toBe('Pending Orders')
        ->and(MenuItem::lens('orders', 'pending-orders', 'Open')->jsonSerialize()['label'])->toBe('Open');
});

it('marks external and new tab links as noopener', function () {
    expect(MenuItem::externalLink('Docs', 'https://lathephp.com')->jsonSerialize())
        ->toMatchArray(['rel' => 'noopener noreferrer'])
        ->and(MenuItem::link('Docs', 'pages/x')->openInNewTab()->jsonSerialize())
        ->toMatchArray(['target' => '_blank', 'rel' => 'noopener noreferrer'])
        ->and(MenuItem::link('Docs', 'pages/x')->target('_parent')->jsonSerialize())
        ->toMatchArray(['target' => '_parent'])
        ->and(MenuItem::link('Docs', 'pages/x')->jsonSerialize())->not->toHaveKey('rel');
});

it('sorts ascending and keeps insertion order on ties', function (array $sorts, array $expected) {
    Menu::using(function (Request $request, Menu $menu) use ($sorts) {
        foreach ($sorts as $label => $sort) {
            $item = MenuItem::link($label, strtolower($label));
            $menu->add($sort === null ? $item : $item->sort($sort));
        }
    });

    expect(array_values(array_diff(builtLabels(), ['Heartbeat'])))->toBe($expected);
})->with([
    'ties and defaults' => [['A' => 5, 'B' => null, 'C' => 5, 'D' => null], ['A', 'C', 'B', 'D']],
    'lower first' => [['A' => 20, 'B' => 10, 'C' => null], ['B', 'A', 'C']],
]);

it('sorts groups and sections the same way', function () {
    Menu::using(fn (Request $request, Menu $menu) => $menu->add(
        MenuGroup::group('G1'),
        MenuSection::section('S1')->sort(1),
        MenuGroup::group('G2')->sort(1),
        MenuItem::link('I1', 'i1'),
    ));

    expect(array_values(array_diff(builtLabels(), ['Heartbeat'])))->toBe(['S1', 'G2', 'G1', 'I1']);
});

it('sorts the items inside a section and a group', function () {
    Menu::using(fn (Request $request, Menu $menu) => $menu->add(
        MenuSection::section('S', [MenuItem::link('B', 'b'), MenuItem::link('A', 'a')->sort(1)]),
        MenuGroup::group('G', [MenuItem::link('Y', 'y'), MenuItem::link('X', 'x')->sort(1)]),
    ));
    $json = array_column(Menu::build(Request::create('/'))->jsonSerialize(), null, 'label');

    expect(array_column($json['S']['items'], 'label'))->toBe(['A', 'B'])
        ->and(array_column($json['G']['items'], 'label'))->toBe(['X', 'Y']);
});

it('hides what canSee refuses', function () {
    Menu::using(fn (Request $request, Menu $menu) => $menu->add(
        MenuItem::link('Hidden item', 'a')->canSee(fn () => false),
        MenuGroup::group('Hidden group')->canSee(fn () => false),
        MenuSection::section('Hidden section')->canSee(fn () => false),
        MenuSection::section('Shown', [MenuItem::link('Inner hidden', 'b')->canSee(fn () => false), MenuItem::link('Inner', 'c')]),
    ));
    $json = array_column(Menu::build(Request::create('/'))->jsonSerialize(), null, 'label');

    expect(array_keys($json))->toBe(['Heartbeat', 'Shown'])
        ->and(array_column($json['Shown']['items'], 'label'))->toBe(['Inner']);
});

it('hides what a denied ability refuses', function () {
    Gate::define('see-menu', fn ($user = null) => false);
    Menu::using(fn (Request $request, Menu $menu) => $menu->add(
        MenuItem::link('Item', 'a')->canSeeWhen('see-menu'),
        MenuGroup::group('Group')->canSeeWhen('see-menu'),
        MenuSection::section('Section')->canSeeWhen('see-menu'),
        MenuItem::link('Open', 'b'),
    ));

    expect(builtLabels())->toBe(['Heartbeat', 'Open']);
});

it('builds groups with their display options', function () {
    $group = MenuGroup::group('Reports', [MenuItem::link('Sales', 'pages/sales')])
        ->item(MenuItem::link('Costs', 'pages/costs'))
        ->icon(Icon::Activity)
        ->collapsible()
        ->collapsedByDefault();

    expect($group->jsonSerialize())->toMatchArray([
        'kind' => 'group',
        'label' => 'Reports',
        'icon' => 'Activity',
        'collapsible' => true,
        'collapsedByDefault' => true,
    ])->and(array_column($group->jsonSerialize()['items'], 'label'))->toBe(['Sales', 'Costs']);
});

it('builds sections with their display options', function () {
    $section = MenuSection::section('Demo')->icon(Icon::Activity)->collapsible()->path('pages/demo')->item(MenuItem::link('One', 'one'));

    expect($section->jsonSerialize())->toBe([
        'label' => 'Demo',
        'icon' => 'Activity',
        'items' => [['label' => 'One', 'href' => '/lathe/one', 'icon' => null]],
        'href' => '/lathe/pages/demo',
        'collapsible' => true,
    ])->and($section->collapsedByDefault()->jsonSerialize())->toMatchArray(['collapsible' => true, 'collapsedByDefault' => true])
        ->and(MenuSection::section('Plain')->jsonSerialize())->toBe(['label' => 'Plain', 'icon' => null, 'items' => []]);
});

it('links section shortcuts', function () {
    expect(MenuSection::page('system-status')->jsonSerialize())->toMatchArray(['label' => 'System Status', 'href' => '/lathe/pages/system-status'])
        ->and(MenuSection::resource('orders')->jsonSerialize())->toMatchArray(['label' => 'Orders', 'href' => '/lathe/resources/orders']);
});

it('maps badge constructors to tokens', function (Closure $make, string $type) {
    expect($make()->jsonSerialize())->toBe(['value' => '3', 'type' => $type]);
})->with([
    'info' => [fn () => Badge::info('3'), 'info'],
    'success' => [fn () => Badge::success(new class implements Stringable
    {
        public function __toString(): string
        {
            return '3';
        }
    }), 'success'],
    'warning' => [fn () => Badge::warning(fn () => 3), 'warning'],
    'destructive' => [fn () => Badge::destructive('3'), 'destructive'],
]);

it('changes a badge type and adds a tooltip', function () {
    expect(Badge::info('9')->type('warning')->tooltip('Open orders')->jsonSerialize())
        ->toBe(['value' => '9', 'type' => 'warning', 'tooltip' => 'Open orders']);
});

it('puts a badge on an item, a group and a section', function () {
    $calls = 0;
    $item = MenuItem::link('Orders', 'resources/orders')->badge(Badge::success(function () use (&$calls) {
        $calls++;

        return 'new';
    }));
    $group = MenuGroup::group('G')->badge('2', 'warning');
    $section = MenuSection::section('S')->badge(Badge::destructive('!'));

    expect($calls)->toBe(0)
        ->and($item->jsonSerialize()['badge'])->toBe(['value' => 'new', 'type' => 'success'])
        ->and($calls)->toBe(1)
        ->and($group->jsonSerialize()['badge'])->toBe(['value' => '2', 'type' => 'warning'])
        ->and($section->jsonSerialize()['badge'])->toBe(['value' => '!', 'type' => 'destructive']);
});

it('keeps a lazy badge as a closure and a ttl', function () {
    $closure = fn () => 5;

    foreach ([MenuItem::link('A', 'a'), MenuGroup::group('G'), MenuSection::section('S')] as $entry) {
        $entry->withBadge($closure, 120);

        expect($entry->lazyBadge())->toBe(['value' => $closure, 'ttl' => 120, 'type' => 'info'])
            ->and($entry->jsonSerialize())->not->toHaveKey('badge');
    }

    expect(MenuItem::link('A', 'a')->withBadge($closure)->lazyBadge()['ttl'])->toBe(60)
        ->and(MenuItem::link('A', 'a')->lazyBadge())->toBeNull();
});

it('carries a server-side active override', function () {
    Menu::using(fn (Request $r, Menu $menu) => $menu->add(
        MenuItem::link('On', 'a')->active(),
        MenuItem::link('Off', 'b')->active(false),
        MenuItem::link('When', 'c')->activeWhen(fn (Request $r) => true),
        MenuItem::link('Unless', 'd')->activeUnless(fn (Request $r) => true),
        MenuItem::link('Auto', 'e'),
    ));

    $json = array_column(Menu::build(Request::create('/'))->jsonSerialize(), null, 'label');

    expect(array_map(fn (string $label) => $json[$label]['active'] ?? null, ['On', 'Off', 'When', 'Unless', 'Auto']))
        ->toBe([true, false, true, false, null]);
});
