<?php

use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use Lathe\Dashboards\DashboardRegistry;
use Lathe\Dashboards\Main;
use Lathe\Exceptions\LatheException;
use Lathe\Lathe;
use Lathe\Tests\Fixtures\Dashboards\Described;
use Lathe\Tests\Fixtures\Dashboards\DescribedFluent;
use Lathe\Tests\Fixtures\Dashboards\Exploding;
use Lathe\Tests\Fixtures\Dashboards\Revenue;
use Lathe\Tests\Fixtures\Dashboards\SalesTwin;
use Workbench\App\Lathe\Dashboards\Sales;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Exploding::$calls = 0;
});

function admin(): User
{
    return User::where('email', 'admin@lathephp.com')->firstOrFail();
}

it('renders the workbench Sales dashboard at the panel home', function () {
    $this->actingAs(admin())->get('/lathe')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Dashboard', false)
        ->where('dashboard.uriKey', 'main')
        ->where('dashboard.name', 'Sales'));

    expect(Lathe::dashboards()->forKey('main'))->toBeInstanceOf(Sales::class);
});

it('renders the built-in Dashboard when no dashboard has the key main', function () {
    Lathe::dashboards()->flush();

    $this->actingAs(admin())->get('/lathe')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('dashboard.uriKey', 'main')
        ->where('dashboard.name', 'Dashboard')
        ->where('widgets', []));

    expect(Lathe::dashboards()->forKey('main'))->toBeInstanceOf(Main::class);
});

it('fails at boot when two dashboards share a key, naming both classes', function () {
    expect(fn () => Lathe::registerDashboards(Revenue::class, SalesTwin::class))
        ->toThrow(LatheException::class, Revenue::class)
        ->and(fn () => Lathe::registerDashboards(SalesTwin::class))
        ->toThrow(LatheException::class, SalesTwin::class);
});

it('treats a repeat registration of one class as a no-op', function () {
    Lathe::registerDashboards(Revenue::class);
    Lathe::registerDashboards(Revenue::class);

    expect(collect(Lathe::dashboards()->all())->filter(fn ($d) => $d instanceof Revenue))->toHaveCount(1);
});

it('registers, finds, lists and flushes dashboards', function () {
    $registry = app(DashboardRegistry::class);
    $registry->flush();
    $registry->register(Revenue::class);

    expect($registry->forKey('sales'))->toBeInstanceOf(Revenue::class)
        ->and($registry->forKey('nope'))->toBeNull()
        ->and(array_map(fn ($d) => $d::uriKey(), $registry->all()))->toBe(['main', 'sales']);

    $registry->flush();

    expect($registry->forKey('sales'))->toBeNull()
        ->and(array_map(fn ($d) => $d::uriKey(), $registry->all()))->toBe(['main']);
});

it('drops the dashboards a user may not see from authorized() and the sidebar', function () {
    Gate::define('view-sales', fn (User $user) => $user->email === 'admin@lathephp.com');
    Lathe::registerDashboards(Revenue::make()->canSeeWhen('view-sales'), Described::make()->canSee(fn () => false));

    $keysFor = fn (string $email) => collect($this->actingAs(User::where('email', $email)->firstOrFail())
        ->get('/lathe')->viewData('page')['props']['navigation'][0]['items'])->pluck('href')->all();

    // The workbench adds dashboards of its own; these assertions cover the two registered here.
    expect($keysFor('admin@lathephp.com'))->toContain('/lathe', '/lathe/dashboards/sales')->not->toContain('/lathe/dashboards/described')
        ->and($keysFor('editor@lathephp.com'))->toContain('/lathe')->not->toContain('/lathe/dashboards/sales', '/lathe/dashboards/described');

    $this->actingAs(admin());
    expect(array_map(fn ($d) => $d::uriKey(), Lathe::dashboards()->authorized(request())))->toContain('main', 'sales')->not->toContain('described');
});

it('runs widgets() only on its own dashboard requests', function () {
    Lathe::registerDashboards(Exploding::class);
    $this->actingAs(admin());

    $this->get('/lathe')->assertOk();
    $this->getJson('/lathe/api/dashboards')->assertOk()->assertJsonFragment(['uriKey' => 'exploding']);
    expect(collect($this->get('/lathe/resources/orders')->assertOk()->viewData('page')['props']['navigation'][0]['items'])->pluck('href'))
        ->toContain('/lathe/dashboards/exploding');

    expect(Exploding::$calls)->toBe(0);
});

it('gives the attribute twins the payload of the fluent calls', function () {
    Lathe::registerDashboards(Described::class, DescribedFluent::class);
    $this->actingAs(admin());

    $payload = fn (string $key) => collect($this->getJson($key)->assertOk()->json('dashboard'))->except(['uriKey', 'name'])->all();

    expect($payload('/lathe/api/dashboards/described'))
        ->toBe($payload('/lathe/api/dashboards/described-fluent'))
        ->toBe(['description' => 'Numbers of the week', 'icon' => 'BarChart', 'showRefreshButton' => true, 'ranges' => [
            ['value' => 7, 'label' => '7 days'],
            ['value' => 30, 'label' => '30 days'],
            ['value' => 365, 'label' => '12 months'],
        ]]);
});

it('answers 404, 403 and 401 for a dashboard', function () {
    Lathe::registerDashboards(Revenue::make()->canSee(fn () => false));

    $this->getJson('/lathe/api/dashboards/sales')->assertUnauthorized();
    $this->getJson('/lathe/api/dashboards')->assertUnauthorized();

    $this->actingAs(admin());
    $this->getJson('/lathe/api/dashboards/nope')->assertNotFound();
    $this->getJson('/lathe/api/dashboards/sales')->assertForbidden();
    $this->get('/lathe/dashboards/nope')->assertNotFound();
    $this->get('/lathe/dashboards/sales')->assertForbidden();
});

it('renders a registered dashboard on its own page', function () {
    Lathe::registerDashboards(Revenue::class);

    $this->actingAs(admin())->get('/lathe/dashboards/sales')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Dashboard', false)
        ->where('dashboard.uriKey', 'sales')
        ->where('dashboard.name', 'Revenue'));
});
