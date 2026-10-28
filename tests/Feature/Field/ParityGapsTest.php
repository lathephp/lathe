<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Lathe\Field\Field;
use Lathe\Filters\FieldOptionsFilter;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class GapOrders extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->title('number')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::text('Number')->sortable()->sortableUriKey('placed_at'),
            Field::select('Status')->filterable(true, ['pending' => 'Pending', 'paid' => 'Paid']),
            Field::text('Big', fn (Order $order) => $order->total_cents > 100000 ? 'big' : 'small')
                ->filterable(fn (LatheRequest $request, Builder $query, mixed $value) => $query->where('total_cents', $value === 'big' ? '>' : '<=', 100000), ['big', 'small']),
        ];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => true);
    Lathe::registerResources(GapOrders::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('takes help from a Closure and sends it as plain text through the sanitizer', function () {
    $payload = Field::text('A')->help(fn () => '<b>Bold</b> <script>x</script>tip')->jsonSerialize();

    expect($payload['help'])->toBe('Bold tip')
        ->and(Field::text('B')->help('<i>Hi</i><script>alert(1)</script>')->jsonSerialize()['help'])->toBe('Hi');
});

it('sorts on the column sortableUriKey names', function () {
    $field = Field::text('Number')->sortableUriKey('placed_at');

    expect($field->getSortableUriKey())->toBe('placed_at')
        ->and($field->jsonSerialize()['sortKey'])->toBe('placed_at')
        ->and($this->getJson('/lathe/api/resources/gap-orders?sort=placed_at')->assertOk()->json('state.sort'))->toBe('placed_at');
});

it('makes a select field filterable by its own options when filterable() names none', function () {
    $request = LatheRequest::createFrom(request());
    $listed = Field::select('Status')->options(['pending' => 'Pending', 'paid' => 'Paid'])->filterable()->makeFieldFilter();
    $named = Field::select('Status')->options(['pending' => 'Pending', 'paid' => 'Paid'])->filterable(options: ['paid' => 'Paid'])->makeFieldFilter();

    expect($listed)->toBeInstanceOf(FieldOptionsFilter::class)
        ->and($listed->options($request))->toBe(['Pending' => 'pending', 'Paid' => 'paid'])
        ->and($named->options($request))->toBe(['Paid' => 'paid']);
});

it('makes a select filter from filterable options and lets a Closure filter a computed field', function () {
    $status = Field::select('Status')->filterable(true, ['pending' => 'Pending'])->makeFieldFilter();

    expect($status)->toBeInstanceOf(FieldOptionsFilter::class)
        ->and($status->options(LatheRequest::createFrom(request())))->toBe(['Pending' => 'pending']);

    $paid = $this->getJson('/lathe/api/resources/gap-orders?filter[field_status]=paid&per_page=100')->assertOk()->json('table.rows');
    $big = $this->getJson('/lathe/api/resources/gap-orders?filter[field_big]=big&per_page=100')->assertOk()->json('table.rows');

    expect(collect($paid)->pluck('cells.status.value')->unique()->values()->all())->toBe(['paid'])
        ->and(collect($big)->pluck('cells.big.value')->unique()->values()->all())->toBe(['big']);
});
