<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Plugins\Plugin;
use Workbench\App\Models\User;

beforeEach(function () {
    Gate::define('viewLathe', fn () => true);
    $this->artisan('migrate')->assertSuccessful();
    $this->admin = User::forceCreate(['name' => 'Admin User', 'email' => 'admin@lathephp.com', 'password' => bcrypt('password')]);
});

function providedProps(?User $user = null): array
{
    $test = $user === null ? test() : test()->actingAs($user);

    return $test->get($user === null ? '/lathe/login' : '/lathe')->assertOk()->viewData('page')['props']['provided'];
}

it('boots outside HTTP without binding a request, so the first real request answers 200', function () {
    $ran = 0;
    Lathe::serving(function () use (&$ran) {
        $ran++;
    });

    Lathe::ensureBooted();
    Lathe::ensureBooted();

    expect($ran)->toBe(1)
        ->and(Lathe::isServing())->toBeFalse()
        ->and(app()->bound(LatheRequest::class))->toBeFalse();
    $this->get('/lathe/login')->assertOk();
});

it('dispatches ServingLathe once per panel request', function () {
    $ran = 0;
    Lathe::serving(function () use (&$ran) {
        $ran++;
    });

    $this->get('/lathe/login')->assertOk();
    expect($ran)->toBe(1);

    $this->get('/lathe/login')->assertOk();
    expect($ran)->toBe(2);

    $this->get('/not-the-panel');
    expect($ran)->toBe(2);
});

it('tells whether a panel request is being served', function () {
    $seen = [];
    Lathe::serving(function () use (&$seen) {
        $seen[] = Lathe::isServing();
        $seen[] = Lathe::whenServing(fn (LatheRequest $request) => $request->path(), fn () => 'default');
    });

    expect(Lathe::isServing())->toBeFalse()
        ->and(Lathe::whenServing(fn () => 'serving', fn (Request $request) => 'default '.$request->path()))->toBe('default /')
        ->and(Lathe::whenServing(fn () => 'serving'))->toBeNull();

    $this->get('/lathe/login')->assertOk();

    expect($seen)->toBe([true, 'lathe/login']);
});

it('runs a booted callback at once when the app has booted', function () {
    $ran = false;
    Lathe::booted(function () use (&$ran) {
        $ran = true;
    });

    expect($ran)->toBeTrue();
});

it('runs the serving hook of the plugins a request is authorized for', function () {
    $served = [];
    Lathe::registerPlugins(new class($served) extends Plugin
    {
        public function __construct(public array &$served)
        {
            //
        }

        public function serving(Request $request): void
        {
            $this->served[] = $request->path();
        }
    });

    Lathe::servePlugins(Request::create('/lathe/x'));

    expect($served)->toBe(['lathe/x']);
});

it('shares what provideToScript() was given at boot with every request', function () {
    Lathe::provideToScript(['a' => 1, 'later' => fn (Request $request) => $request->path()]);

    expect(providedProps())->toBe(['a' => 1, 'later' => 'lathe/login'])
        ->and(providedProps($this->admin))->toBe(['a' => 1, 'later' => 'lathe']);
});

it('keeps what serving() provides to that request only', function () {
    $calls = 0;
    Lathe::serving(function () use (&$calls) {
        $calls++;

        if ($calls === 1) {
            Lathe::provideToScript(['once' => fn (Request $request) => $request->path()]);
        }
    });

    expect(providedProps())->toBe(['once' => 'lathe/login'])
        ->and(providedProps())->toBe([]);
});

it('refuses to change the panel definition while serving', function (string $setter) {
    $thrown = null;
    Lathe::serving(function () use ($setter, &$thrown) {
        try {
            Lathe::$setter(fn () => 'fr');
        } catch (LogicException $e) {
            $thrown = $e;
        }
    });

    $this->get('/lathe/login')->assertOk();

    expect($thrown)->toBeInstanceOf(LogicException::class);
})->with([
    'auth' => 'auth',
    'footer' => 'footer',
    'userLocale' => 'userLocale',
]);

it('shares what a plugin provides from serving() with that page', function () {
    $plugin = new class extends \Lathe\Plugins\Plugin
    {
        public static function uriKey(): string
        {
            return 'provider';
        }

        public function serving(Request $request): void
        {
            Lathe::provideToScript(['viewer' => $request->user()?->getAttribute('email')]);
        }
    };
    Lathe::registerPlugins($plugin);

    expect(providedProps($this->admin))->toBe(['viewer' => 'admin@lathephp.com']);
});
