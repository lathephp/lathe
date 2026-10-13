<?php

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Inertia\ResponseFactory;
use Lathe\Http\Middleware\HandleInertiaRequests;
use Lathe\Http\Payloads\IndexPayload;
use Workbench\App\Lathe\Resources\Order;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    Gate::define('viewLathe', fn () => true);

    // Inertia merges page props over shared props, so the page never sees a shared prop it shadows.
    $this->pageProps = new ArrayObject;
    $pageProps = $this->pageProps;
    app()->singleton(ResponseFactory::class, fn () => new class($pageProps) extends ResponseFactory
    {
        public function __construct(protected ArrayObject $pageProps)
        {
            //
        }

        public function render($component, $props = []): Response
        {
            $this->pageProps[$component] = array_keys($props instanceof Arrayable ? $props->toArray() : $props);

            return parent::render($component, $props);
        }
    });
    Facade::clearResolvedInstance(ResponseFactory::class);
});

it('gives no page a prop named like a shared prop', function () {
    $this->get('/lathe/login')->assertOk();

    $this->actingAs(User::forceCreate(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => bcrypt('secret')]));
    $this->get('/lathe')->assertOk();
    $this->get('/lathe/resources/orders')->assertOk();
    $this->get('/lathe/resources/orders?page=401')->assertStatus(422);
    $this->get('/lathe/plugins/heartbeat')->assertOk();
    $this->get('/lathe/nowhere')->assertNotFound();

    $middleware = app(HandleInertiaRequests::class);
    $request = Request::create('/lathe');
    $shared = array_keys([...$middleware->share($request), ...$middleware->shareOnce($request)]);

    expect(array_keys($this->pageProps->getArrayCopy()))->toContain('Auth/Login', 'Dashboard', 'Resources/Index', 'Error', 'Plugin');

    foreach ($this->pageProps as $component => $keys) {
        expect(array_values(array_intersect($keys, $shared)))->toBe([], "{$component} shadows a shared prop");
    }
});

it('sends every part of the index payload to the index page', function () {
    $this->actingAs(User::forceCreate(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => bcrypt('secret')]));
    $payload = app(IndexPayload::class)->build(Order::class);

    $this->get('/lathe/resources/orders')->assertOk();

    expect(array_values(array_diff(array_keys($payload), $this->pageProps['Resources/Index'])))->toBe([]);
});
