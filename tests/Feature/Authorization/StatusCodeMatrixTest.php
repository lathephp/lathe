<?php

use Illuminate\Support\Facades\Gate;
use Lathe\Lathe;
use Lathe\Tests\Fixtures\Dashboards\Revenue;
use Lathe\Tests\Fixtures\Pages\Closed;
use Lathe\Tests\Fixtures\Plugins\Beacon;
use Lathe\Tests\Fixtures\Resources\GatedOrder;
use Lathe\Tests\Fixtures\Resources\HiddenOrder;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(GatedOrder::class, HiddenOrder::class);
    Lathe::registerDashboards(Revenue::make()->canSee(fn () => false));
    Lathe::registerPages(Closed::class);
    Lathe::registerPlugins(Beacon::make()->canSee(fn () => false));
    reloadLatheRoutes();
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

dataset('target kinds', [
    'resource' => ['GET', '/lathe/api/resources/nope', '/lathe/api/resources/hidden-orders', null],
    'lens' => ['GET', '/lathe/api/resources/gated-orders/lenses/nope', '/lathe/api/resources/gated-orders/lenses/counted-orders', null],
    'dashboard' => ['GET', '/lathe/api/dashboards/nope', '/lathe/api/dashboards/sales', null],
    'widget' => ['GET', '/lathe/api/resources/gated-orders/widgets/nope', '/lathe/api/resources/gated-orders/widgets/secret', null],
    'action' => ['POST', '/lathe/api/resources/gated-orders/action', '/lathe/api/resources/gated-orders/action', 'action'],
    'page' => ['GET', '/lathe/pages/nope', '/lathe/pages/closed', null],
    'plugin' => ['GET', '/lathe/plugins/nobody', '/lathe/plugins/beacon', null],
    'plugin API' => ['GET', '/lathe/api/plugins/nobody/status', '/lathe/api/plugins/beacon/status', null],
]);

it('answers 404 for an unknown target and 403 for one the user may not see (pin)', function (string $method, string $unknown, string $hidden, ?string $key) {
    // An action is named in the body, not the path.
    $body = fn (string $name) => $key === null ? [] : ['action' => $name, 'selection' => ['mode' => 'ids', 'ids' => [1]], 'fields' => []];

    $this->json($method, $unknown, $body('nope'))->assertNotFound();
    $this->json($method, $hidden, $body('secret'))->assertForbidden();
})->with('target kinds');
