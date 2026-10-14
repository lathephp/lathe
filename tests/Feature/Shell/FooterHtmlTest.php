<?php

use Lathe\Lathe;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->admin = User::forceCreate(['name' => 'Admin User', 'email' => 'admin@lathephp.com', 'password' => bcrypt('password')]);
});

function footerProp(): mixed
{
    return test()->actingAs(test()->admin)->get('/lathe')->assertOk()->viewData('page')['props']['footer'];
}

it('replaces the footer with the HTML a callback returns', function () {
    Lathe::footer(fn () => '<a href="/x">Terms</a>');

    expect(footerProp())->toBe(['html' => ['__html' => '<a href="/x">Terms</a>']]);
});

it('strips a script out of the footer HTML', function () {
    Lathe::footer(fn () => 'Terms<script>alert(1)</script>');

    expect(footerProp())->toBe(['html' => ['__html' => 'Terms']]);
});
