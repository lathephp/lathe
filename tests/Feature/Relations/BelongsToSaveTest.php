<?php

namespace Lathe\Tests\Feature\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class SaveLog
{
    /** @var list<array{mixed, bool}> */
    public static array $filled = [];
}

class SaveCustomer extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Customer::class)->title('name')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::text('Name')];
    }
}

/** An owner row whose customer link may be empty. */
class SaveOwner extends Model
{
    protected $table = 'save_owners';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}

/** Nullable customer, with the picker narrowed to US customers. */
class SaveOwnerResource extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(SaveOwner::class)->title('id')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::belongsTo('Customer')->resource(SaveCustomer::class)->nullable()
                ->relatableQuery(fn (LatheRequest $request, Builder $query) => $query->where('country', 'US'))
                ->filled(function (LatheRequest $request, Model $model) {
                    SaveLog::$filled[] = [$model->getAttribute('customer_id'), $model->exists];
                }),
        ];
    }
}

/** A policy that knows create but not addCustomer, so relating is denied. */
class SaveDeniedPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }
}

class SaveDeniedResource extends SaveOwnerResource
{
    public static ?string $policy = SaveDeniedPolicy::class;

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(SaveOwner::class)->title('id')->canonical(false);
    }

    public static function uriKey(): string
    {
        return 'save-denied-resources';
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Schema::create('save_owners', function (Blueprint $table) {
        $table->id();
        $table->foreignId('customer_id')->nullable();
    });
    SaveLog::$filled = [];
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(SaveCustomer::class, SaveOwnerResource::class, SaveDeniedResource::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

/**
 * @return array<string, mixed>
 */
function orderPayload(string $number, mixed $customer = null): array
{
    return array_filter([
        'number' => $number,
        'status' => 'pending',
        'placed_at' => '2026-05-01 10:00:00',
        'total_cents' => 12500,
        'customer' => $customer,
    ], fn (mixed $value) => $value !== null);
}

it('creates an order with the chosen customer and shows the customer on the index', function () {
    $customer = Customer::query()->where('name', 'like', 'Ann%')->orderBy('id')->firstOrFail();

    $this->postJson('/lathe/api/resources/orders', orderPayload('SAVE-0001', ['value' => $customer->id]))->assertCreated();

    $order = Order::query()->where('number', 'SAVE-0001')->firstOrFail();
    expect($order->customer_id)->toBe($customer->id);

    $rows = $this->getJson('/lathe/api/resources/orders?search=SAVE-0001')->assertOk()->json('table.rows');
    expect(json_encode($rows))->toContain($customer->name);
});

it('requires a customer on an order', function () {
    $orders = Order::query()->count();

    $this->postJson('/lathe/api/resources/orders', orderPayload('SAVE-0002'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('customer');

    expect(Order::query()->count())->toBe($orders);
});

it('accepts a bare key like {value: key}', function () {
    $this->postJson('/lathe/api/resources/orders', orderPayload('SAVE-0003', 12))->assertCreated();

    expect(Order::query()->where('number', 'SAVE-0003')->value('customer_id'))->toBe(12);
});

it('refuses a customer outside the picker query and one that does not exist', function (mixed $id) {
    $response = $this->postJson('/lathe/api/resources/save-owner-resources', ['customer' => ['value' => value($id)]])
        ->assertUnprocessable();

    expect($response->json('errors.customer.0'))->toEndWith('must be a valid relation.')
        ->and(SaveOwner::query()->count())->toBe(0);
})->with([
    'excluded by relatableQuery' => fn () => Customer::query()->where('country', '!=', 'US')->value('id'),
    'missing' => 999999,
]);

it('refuses to relate when the policy has no add method', function () {
    $customer = Customer::query()->where('country', 'US')->firstOrFail();

    $response = $this->postJson('/lathe/api/resources/save-denied-resources', ['customer' => ['value' => $customer->id]])
        ->assertUnprocessable();

    expect($response->json('errors.customer.0'))->toBe('You are not authorized to relate this resource.');
});

it('saves a null key for an empty nullable value', function () {
    $this->postJson('/lathe/api/resources/save-owner-resources', ['customer' => ['value' => '']])->assertCreated();

    expect(SaveOwner::query()->firstOrFail()->customer_id)->toBeNull();
});

it('runs filled once, after the key is written', function () {
    $customer = Customer::query()->where('country', 'US')->firstOrFail();

    $this->postJson('/lathe/api/resources/save-owner-resources', ['customer' => ['value' => $customer->id]])->assertCreated();

    expect(SaveLog::$filled)->toBe([[$customer->id, false]]);
});

it('replicates an order with its customer', function () {
    $source = Order::query()->findOrFail(12);
    $customer = Customer::query()->findOrFail($source->customer_id);

    $fields = $this->getJson('/lathe/api/resources/orders/12/replicate-fields')->assertOk()->json('fields');
    $value = collect($fields)->firstWhere('key', 'customer')['value'];

    expect($value['value'])->toBe($customer->id)
        ->and($value['display'])->toBe($customer->name);

    $this->postJson('/lathe/api/resources/orders', orderPayload('SAVE-0004', $value))->assertCreated();

    expect(Order::query()->where('number', 'SAVE-0004')->value('customer_id'))->toBe($customer->id);
});
