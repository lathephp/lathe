<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lathe\Auth\PanelGate;
use Lathe\Lathe;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->loadLaravelMigrations();

    config(['auth.guards.panel' => ['driver' => 'session', 'provider' => 'users'], 'lathe.guard' => 'panel']);
    $this->admin = User::factory()->create(['email' => 'admin@lathephp.com']);
    $this->guest = User::factory()->create(['email' => 'guest@lathephp.com']);
});

it('returns false for a request with no user, even when the callback admits', function () {
    $called = false;
    Lathe::auth(function () use (&$called) {
        $called = true;

        return true;
    });

    expect(Lathe::check(Request::create('/lathe')))->toBeFalse()
        ->and($called)->toBeFalse();
});

it('asks the viewLathe gate for the user of the panel guard', function () {
    Gate::define('viewLathe', fn (User $user) => $user->email === 'admin@lathephp.com');

    auth('panel')->setUser($this->admin);
    expect(Lathe::check(Request::create('/lathe')))->toBeTrue();

    auth('panel')->setUser($this->guest);
    expect(Lathe::check(Request::create('/lathe')))->toBeFalse();
});

it('ignores a user signed in on another guard', function () {
    Gate::define('viewLathe', fn (User $user) => $user->email === 'admin@lathephp.com');
    auth('web')->setUser($this->admin);

    expect(Lathe::check(Request::create('/lathe')))->toBeFalse();
});

it('runs the callback with the request instead of the gate', function () {
    $request = Request::create('/lathe');
    $seen = null;
    Lathe::auth(function (Request $given) use (&$seen) {
        $seen = $given;

        return true;
    });

    auth('panel')->setUser($this->guest);
    expect(Lathe::check($request))->toBeTrue()
        ->and($seen)->toBe($request);

    Lathe::auth(fn () => false);
    auth('panel')->setUser($this->admin);
    expect(Lathe::check($request))->toBeFalse();
});

it('admits only on a callback that returns true itself', function () {
    auth('panel')->setUser($this->admin);

    Lathe::auth(fn () => 1);

    expect(app(PanelGate::class)->check(Request::create('/lathe')))->toBeFalse();
});

it('denies when the viewLathe gate is not defined', function () {
    auth('panel')->setUser($this->admin);

    expect(Gate::has('viewLathe'))->toBeFalse()
        ->and(Lathe::check(Request::create('/lathe')))->toBeFalse();
});
