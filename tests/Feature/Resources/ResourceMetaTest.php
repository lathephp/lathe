<?php

namespace Lathe\Tests\Feature\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class MetaOrders extends Resource
{
    public function __construct(?Model $model = null)
    {
        parent::__construct($model);

        $this->withMeta(['tier' => 'gold']);
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }

    public static function additionalInformation(LatheRequest $request): array
    {
        return ['viewer' => $request->user()?->email];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(MetaOrders::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('merges meta over what the Resource already holds', function () {
    $resource = MetaOrders::make()->withMeta(['region' => 'eu'])->withMeta(['tier' => 'silver']);

    expect($resource->meta())->toBe(['tier' => 'silver', 'region' => 'eu']);
});

it('puts meta and additional information in the Resource part of every page', function (string $path) {
    $order = Order::query()->firstOrFail();

    $this->get(str_replace('{id}', (string) $order->getKey(), $path))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('resource.meta', ['tier' => 'gold'])
            ->where('resource.extra', ['viewer' => 'admin@lathephp.com']));
})->with([
    'index' => ['/lathe/resources/meta-orders'],
    'detail' => ['/lathe/resources/meta-orders/{id}'],
    'edit' => ['/lathe/resources/meta-orders/{id}/edit'],
    'create' => ['/lathe/resources/meta-orders/create'],
]);

it('gives a Resource without meta or additional information empty lists', function () {
    $this->get('/lathe/resources/orders')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('resource.meta', [])
            ->where('resource.extra', []));
});
