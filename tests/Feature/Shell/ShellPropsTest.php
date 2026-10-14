<?php

use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use Lathe\Lathe;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->admin = User::forceCreate(['name' => 'Admin User', 'email' => 'admin@lathephp.com', 'password' => bcrypt('password')]);
});

function shellProps(array $cookies = []): array
{
    return test()->actingAs(test()->admin)->withUnencryptedCookies($cookies)->get('/lathe')->assertOk()->viewData('page')['props'];
}

it('opens the sidebar from the layout default without a cookie', function () {
    expect(shellProps()['sidebarOpen'])->toBeTrue();

    config(['lathe.layout.sidebar.default_open' => false]);
    expect(shellProps()['sidebarOpen'])->toBeFalse();
});

it('opens the sidebar from the cookie', function (string $cookie, bool $open) {
    config(['lathe.layout.sidebar.default_open' => ! $open]);

    expect(shellProps(['sidebar_state' => $cookie])['sidebarOpen'])->toBe($open);
})->with([['false', false], ['true', true]]);

it('labels the version from the config', function () {
    config(['lathe.version' => 'v2 beta']);
    expect(shellProps()['versionLabel'])->toBe('v2 beta');

    config(['lathe.version' => false]);
    expect(shellProps()['versionLabel'])->toBeNull();
});

it('shows the installed version when the label is null', function () {
    config(['lathe.version' => null]);

    expect(shellProps()['versionLabel'])->toBe(Lathe::version());
});

it('reads the footer from Lathe::footer(), and an empty string hides it', function () {
    expect(shellProps()['footer'])->toBe(['text' => 'Powered by Lathe', 'href' => 'https://lathephp.com']);

    Lathe::footer(fn () => 'My footer');
    expect(shellProps()['footer'])->toBe(['html' => ['__html' => 'My footer']]);

    Lathe::footer(fn () => '');
    expect(shellProps()['footer'])->toBeNull();
});

it('shares the layout and table settings', function () {
    config(['lathe.layout.content.width' => 'contained', 'lathe.layout.header.sticky' => false, 'lathe.layout.sidebar.collapsible' => 'none', 'lathe.table.density' => 'compact', 'lathe.table.striped' => true]);

    $props = shellProps();

    expect($props['layout'])->toBe([
        'sidebar' => ['collapsible' => 'none', 'width' => '16rem', 'widthIcon' => '3rem'],
        'header' => ['sticky' => false],
        'content' => ['width' => 'contained'],
    ])->and($props['tableStyle'])->toBe(['density' => 'compact', 'striped' => true]);
});

it('shares whether the palette is on and how long typing waits', function () {
    expect(shellProps()['globalSearch'])->toBe(['enabled' => true, 'debounce' => 0.5]);

    config(['lathe.global_search.debounce' => 0.2]);
    expect(shellProps()['globalSearch'])->toBe(['enabled' => true, 'debounce' => 0.2]);

    config(['lathe.global_search.enabled' => false]);
    expect(shellProps()['globalSearch']['enabled'])->toBeFalse();
});

it('turns the palette off for a user without the globalSearch ability', function () {
    Gate::define('globalSearch', fn () => false);

    expect(shellProps()['globalSearch']['enabled'])->toBeFalse();
});

it('renders the dashboard at the key main', function () {
    $this->actingAs($this->admin)->get('/lathe')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Dashboard', false)
        ->where('dashboard.uriKey', 'main')
        ->where('dashboard.name', 'Sales')
        ->has('widgets', 3));
});
