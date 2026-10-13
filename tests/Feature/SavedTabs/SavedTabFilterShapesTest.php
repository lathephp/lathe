<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Lathe\Filters\BooleanFilter;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Lathe\Resources\Order as OrderResource;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class SavedTabShapesFlags extends BooleanFilter
{
    public function key(): string
    {
        return 'flags';
    }

    public function options(LatheRequest $request): array
    {
        return ['Paid' => 'paid', 'Shipped' => 'shipped'];
    }

    public function apply(LatheRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->whereIn($query->qualifyColumn('status'), array_keys(array_filter($value)));
    }
}

class SavedTabShapesOrder extends OrderResource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canonical(false);
    }

    public function filters(LatheRequest $request): array
    {
        return [...parent::filters($request), new SavedTabShapesFlags];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(SavedTabShapesOrder::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('keeps a range and a boolean filter it saved, applied and not stale', function (array $filters, array $stored, Closure $matches) {
    $id = $this->postJson('/lathe/api/resources/saved-tab-shapes-orders/saved-tabs', ['name' => 'Shape', 'filters' => $filters])
        ->assertCreated()
        ->json('id');

    $json = $this->getJson("/lathe/api/resources/saved-tab-shapes-orders?saved_tab={$id}&per_page=100")->assertOk()->json();
    $orders = Order::whereIn('id', array_column($json['table']['rows'], 'id'))->get()->all();

    expect($orders)->not->toBeEmpty()
        ->and(array_filter($orders, fn (Order $order) => ! $matches($order)))->toBe([])
        ->and($json['tabs']['saved'][0]['stale'])->toBeFalse()
        ->and($json['tabs']['saved'][0]['filters'])->toBe($stored);
})->with([
    'range' => [
        ['field_total_cents' => ['min' => '100000']],
        ['field_total_cents' => ['min' => 100000, 'max' => null]],
        fn (Order $o) => $o->total_cents >= 100000,
    ],
    'boolean' => [
        ['flags' => ['paid' => '1', 'shipped' => '0']],
        ['flags' => ['paid' => true, 'shipped' => false]],
        fn (Order $o) => $o->status === 'paid',
    ],
]);
