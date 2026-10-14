<?php

namespace Lathe\Tests\Feature\Relations;

use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Lathe\Resources\OrderItem;
use Workbench\App\Models\Customer as CustomerModel;
use Workbench\App\Models\Order as OrderModel;
use Workbench\App\Models\OrderItem as OrderItemModel;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

/** Orders that hide every record from view, though items may still be added. */
class HiddenOrderPolicy
{
    public function view(User $user, OrderModel $order): bool
    {
        return false;
    }

    public function addItems(User $user, OrderModel $order): bool
    {
        return true;
    }
}

class ViaHiddenOrders extends Resource
{
    public static ?string $policy = HiddenOrderPolicy::class;

    public static function uriKey(): string
    {
        return 'via-hidden-orders';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(OrderModel::class)->title('number')->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::hasMany('Items')->resource(OrderItem::class)];
    }
}

/** Customers whose orders anyone may add, so the workbench Order form opens under a via. */
class ViaCustomers extends Resource
{
    public static function uriKey(): string
    {
        return 'via-customers';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(CustomerModel::class)->title('name')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::text('Name'), Field::hasMany('Orders')];
    }
}

/** Items whose Order field has a default the via must beat. */
class ViaDefaultItems extends Resource
{
    public static function uriKey(): string
    {
        return 'via-default-items';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(OrderItemModel::class)->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::belongsTo('Order')->default(13), Field::number('Quantity')];
    }
}

class ViaDefaultOrders extends Resource
{
    public static function uriKey(): string
    {
        return 'via-default-orders';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(OrderModel::class)->title('number')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::hasMany('Items')->resource(ViaDefaultItems::class)];
    }
}

/** Items whose Product field names the parent's items relation as its inverse. */
class ViaInverseItems extends Resource
{
    public static function uriKey(): string
    {
        return 'via-inverse-items';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(OrderItemModel::class)->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::belongsTo('Order'), Field::belongsTo('Product')->inverse('items')];
    }
}

class ViaInverseOrders extends Resource
{
    public static function uriKey(): string
    {
        return 'via-inverse-orders';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(OrderModel::class)->title('number')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::hasMany('Items')->resource(ViaInverseItems::class)];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(ViaHiddenOrders::class, ViaCustomers::class, ViaDefaultItems::class, ViaDefaultOrders::class, ViaInverseItems::class, ViaInverseOrders::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

/**
 * The field of a create form with the given key.
 *
 * @param  array<string, mixed>  $form
 * @return array<string, mixed>
 */
function viaField(array $form, string $key): array
{
    return collect($form['fields'])->firstWhere('key', $key);
}

it('locks the order field of the item form to the order of the via', function () {
    $form = $this->getJson('/lathe/api/resources/order-items/creation-fields?via=orders/12/items')->assertOk()->json();

    expect(viaField($form, 'order'))->toMatchArray(['readonly' => true, 'value' => ['value' => 12, 'display' => 'ORD-00099454']])
        ->and(viaField($form, 'product')['readonly'])->toBeFalse();
});

it('locks the order field on the create page too', function () {
    $this->get('/lathe/resources/order-items/new?via=orders/12/items')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('form.fields.0.key', 'order')
            ->where('form.fields.0.readonly', true)
            ->where('form.fields.0.value', ['value' => 12, 'display' => 'ORD-00099454']));
});

it('answers the create page under an invalid via with the not found page', function () {
    $this->get('/lathe/resources/order-items/new?via=orders/12/nothing')->assertNotFound();
});

it('lets the via beat a field default', function () {
    $form = $this->getJson('/lathe/api/resources/via-default-items/creation-fields?via=via-default-orders/12/items')->assertOk()->json();

    expect(viaField($form, 'order'))->toMatchArray(['readonly' => true, 'value' => ['value' => 12, 'display' => 'ORD-00099454']]);
});

it('saves the item under the order of the via whatever order the body names', function () {
    $response = $this->postJson('/lathe/api/resources/order-items?via=orders/12/items', [
        'order' => ['value' => 13],
        'product' => ['value' => 1],
        'quantity' => 2,
        'price_cents' => 500,
    ])->assertCreated();

    expect(OrderItemModel::query()->findOrFail($response->json('id'))->order_id)->toBe(12);
});

it('refuses an invalid via with a via error', function (string $via) {
    $this->getJson('/lathe/api/resources/order-items/creation-fields?via='.$via)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('via');
})->with([
    'missing parent record' => 'orders/99999999/items',
    'unknown relationship' => 'orders/12/nothing',
    'two segments' => 'orders/12',
    'parent the user may not view' => 'via-hidden-orders/12/items',
]);

it('refuses a save under an invalid via', function () {
    $this->postJson('/lathe/api/resources/order-items?via=orders/99999999/items', [
        'order' => ['value' => 13],
        'product' => ['value' => 1],
        'quantity' => 2,
        'price_cents' => 500,
    ])->assertUnprocessable()->assertJsonValidationErrors('via');
});

it('forbids a via to a user who may not add items to the order', function () {
    $this->actingAs(User::where('email', 'editor@lathephp.com')->firstOrFail());

    $this->getJson('/lathe/api/resources/order-items/creation-fields?via=orders/12/items')->assertForbidden();
});

it('matches the child field by the foreign key of the relation', function () {
    $form = $this->getJson('/lathe/api/resources/orders/creation-fields?via=via-customers/12/orders')->assertOk()->json();

    expect(viaField($form, 'customer'))->toMatchArray([
        'readonly' => true,
        'value' => ['value' => 12, 'display' => CustomerModel::query()->findOrFail(12)->name],
    ]);
});

it('matches the field naming the relation as its inverse before a foreign key match', function () {
    $form = $this->getJson('/lathe/api/resources/via-inverse-items/creation-fields?via=via-inverse-orders/12/items')->assertOk()->json();

    expect(viaField($form, 'product')['readonly'])->toBeTrue()
        ->and(viaField($form, 'product')['value']['value'])->toBe(12)
        ->and(viaField($form, 'order')['readonly'])->toBeFalse()
        ->and(viaField($form, 'order')['value'])->toBeNull();
});
