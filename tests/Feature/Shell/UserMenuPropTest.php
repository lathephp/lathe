<?php

use Illuminate\Http\Request;
use Lathe\Menu\Menu;
use Lathe\Menu\MenuItem;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->admin = User::forceCreate(['name' => 'Admin User', 'email' => 'admin@lathephp.com', 'password' => bcrypt('password')]);
});

function userMenuProp(): mixed
{
    return test()->actingAs(test()->admin)->get('/lathe')->assertOk()->viewData('page')['props']['userMenu'];
}

it('lists the Menu::userMenu() items and a Security entry when passwords can be updated', function () {
    Menu::userMenu(fn (Request $request, Menu $menu) => $menu->add(MenuItem::link('Profile', 'profile')));
    config(['lathe.fortify.features' => ['update-passwords']]);

    expect(userMenuProp())->toBe([
        ['label' => 'Profile', 'href' => '/lathe/profile', 'icon' => null],
        ['label' => 'Security', 'href' => '/lathe/security', 'icon' => 'Shield'],
    ]);
});

it('lists the Security entry for two factor authentication alone', function () {
    config(['lathe.fortify.features' => ['two-factor-authentication']]);

    expect(array_column(userMenuProp(), 'label'))->toBe(['Security']);
});

it('has no Security entry when no Fortify feature is on', function () {
    Menu::userMenu(fn (Request $request, Menu $menu) => $menu->add(MenuItem::link('Profile', 'profile')));
    config(['lathe.fortify.features' => []]);

    expect(array_column(userMenuProp(), 'label'))->toBe(['Profile']);
});
