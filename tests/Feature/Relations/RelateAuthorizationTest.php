<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(RelateAuthorizationOneField::class, RelateAuthorizationThreeFields::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

/** Orders with one relation field, authorized by OrderPolicy. */
class RelateAuthorizationOneField extends Resource
{
    public static function uriKey(): string
    {
        return 'relate-authorization-one';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::hasMany('Items')];
    }
}

/**
 * Orders with three relation fields, authorized by OrderPolicy. No belongsTo, whose eager
 * load on the find would add a query that has nothing to do with the policy calls.
 */
class RelateAuthorizationThreeFields extends RelateAuthorizationOneField
{
    public static function uriKey(): string
    {
        return 'relate-authorization-three';
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::hasMany('Items'), Field::hasMany('Notes'), Field::hasMany('Lines')];
    }
}

it('answers the add and attach flags of every relation field of an order', function () {
    $this->getJson('/lathe/api/resources/orders/relate-authorization?resourceId=12')
        ->assertOk()
        ->assertExactJson([
            'customer' => ['add' => true, 'attach' => false, 'attachAny' => false, 'detach' => false],
            'items' => ['add' => true, 'attach' => false, 'attachAny' => false, 'detach' => false],
            'actions' => ['add' => false, 'attach' => false, 'attachAny' => false, 'detach' => false],
        ]);
});

it('denies adding items to an editor', function () {
    $this->actingAs(User::where('email', 'editor@lathephp.com')->firstOrFail());

    $this->getJson('/lathe/api/resources/orders/relate-authorization?resourceId=12')
        ->assertOk()
        ->assertJsonPath('items.add', false);
});

it('lets only an admin add orders to a customer', function () {
    $this->getJson('/lathe/api/resources/customers/relate-authorization?resourceId=12')
        ->assertOk()
        ->assertJsonPath('orders.add', true);

    $this->actingAs(User::where('email', 'editor@lathephp.com')->firstOrFail());

    $this->getJson('/lathe/api/resources/customers/relate-authorization?resourceId=12')
        ->assertOk()
        ->assertJsonPath('orders.add', false);
});

it('runs the same query count for one relation field and for three', function () {
    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->getJson('/lathe/api/resources/relate-authorization-one/relate-authorization?resourceId=12')->assertOk();
    $one = $queries;
    $queries = 0;
    $this->getJson('/lathe/api/resources/relate-authorization-three/relate-authorization?resourceId=12')->assertOk();

    // The find is the only query; the policy calls run in memory.
    expect($one)->toBe(1)->and($queries)->toBe(1);
});
