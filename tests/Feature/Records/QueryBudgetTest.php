<?php

namespace Lathe\Tests\Feature\Records;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\Order as OrderModel;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

/** Orders anyone may create, so creation-fields has a budget to measure. */
class BudgetOrder extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(OrderModel::class)->title('number')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::make('Number'), Field::make('Status')];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(BudgetOrder::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());

    // Order 12 holds one item and order 13 holds five.
    DB::table('order_items')->whereIn('order_id', [12, 13])->delete();
    foreach ([12 => 1, 13 => 5] as $order => $count) {
        foreach (range(1, $count) as $line) {
            DB::table('order_items')->insert(['order_id' => $order, 'product_id' => $line, 'quantity' => 1, 'price_cents' => 100]);
        }
    }

    Model::preventLazyLoading();
});

afterEach(function () {
    Model::preventLazyLoading(false);
});

function recordQueriesDuring(Closure $request): int
{
    $count = 0;
    DB::listen(function () use (&$count) {
        $count++;
    });
    $request();

    return $count;
}

it('runs the same pinned query count for an order with one item and with five', function (string $path, int $budget) {
    $one = recordQueriesDuring(fn () => $this->get(str_replace('{id}', '12', $path))->assertOk());
    $five = recordQueriesDuring(fn () => $this->get(str_replace('{id}', '13', $path))->assertOk());

    expect($one)->toBe($budget)->and($five)->toBe($budget);
})->with([
    // Detail: the order and its customer; the items load in their panel. Edit: the order,
    // its customer and one query for the repeater's items, however many.
    'detail' => ['/lathe/resources/orders/{id}', 2],
    'edit' => ['/lathe/resources/orders/{id}/edit', 3],
    'creation-fields' => ['/lathe/api/resources/budget-orders/creation-fields', 0],
]);
