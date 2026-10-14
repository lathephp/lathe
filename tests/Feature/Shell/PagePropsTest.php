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
use Workbench\Database\Seeders\DemoSeeder;

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

    // The page adds the widget list to the payload.
    expect(array_values(array_diff([...array_keys($payload), 'widgets'], $this->pageProps['Resources/Index'])))->toBe([]);
});

it('gives the record pages no prop named like a shared prop', function () {
    $this->seed(DemoSeeder::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());

    $this->get('/lathe/resources/orders/12')->assertOk();
    $this->get('/lathe/resources/orders/12/edit')->assertOk();

    $middleware = app(HandleInertiaRequests::class);
    $request = Request::create('/lathe');
    $shared = array_keys([...$middleware->share($request), ...$middleware->shareOnce($request)]);

    foreach (['Resources/Detail', 'Resources/Form'] as $component) {
        expect(array_values(array_intersect($this->pageProps[$component], $shared)))->toBe([], "{$component} shadows a shared prop");
    }
});

it('renders lang and dir from the shared rtl prop for the workbench ar context', function () {
    $ltr = $this->get('/lathe/login')->assertOk()->getContent();
    $rtl = $this->get('/lathe/login', ['X-Bench-Locale' => 'ar'])->assertOk()->getContent();
    $next = $this->get('/lathe/login')->assertOk()->getContent();

    expect($ltr)->toContain('<html lang="en" dir="ltr">')->toContain('"rtl":false')
        ->and($rtl)->toContain('<html lang="ar" dir="rtl">')->toContain('"rtl":true')->toContain('"locale":"ar"')
        ->and($next)->toContain('<html lang="en" dir="ltr">')
        ->and(app()->getLocale())->toBe('en');
});

it('sends the action menu to the lens page', function () {
    $this->actingAs(User::forceCreate(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => bcrypt('secret')]));

    $this->get('/lathe/resources/orders/lens/pending-orders')->assertOk();

    expect($this->pageProps['Resources/Index'])->toContain('actions');
});
