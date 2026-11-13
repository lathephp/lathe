<?php

namespace Lathe\Tests\Feature\Relations\PickerRelatedAccess;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Lathe\Actions\Action;
use Lathe\Actions\ActionFields;
use Lathe\Actions\ActionResult;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Testing\LatheTest;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class GuardedSetup
{
    public static string $viewable = '';
}

/** Lists no customer, and lets the user view only the one whose email GuardedSetup names. */
class GuardedCustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Customer $customer): bool
    {
        return $customer->email === GuardedSetup::$viewable;
    }
}

class HiddenCustomers extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Customer::class)->title('name')->canonical(false)->allowAll()->canSee(fn () => false);
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::text('Name')];
    }
}

class GuardedCustomers extends Resource
{
    public static ?string $policy = GuardedCustomerPolicy::class;

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Customer::class)->title('name')->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::text('Name')];
    }
}

class AssignHiddenCustomer extends Action
{
    public function fields(LatheRequest $request): array
    {
        return [Field::belongsTo('Customer', 'customer', HiddenCustomers::class)];
    }

    public function handle(ActionFields $fields, Collection $models): ActionResult
    {
        return ActionResult::message('Assigned.');
    }
}

class HiddenCustomerOrders extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->title('number')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::text('Number'), Field::belongsTo('Customer', 'customer', HiddenCustomers::class)->filterable()];
    }

    public function actions(LatheRequest $request): array
    {
        return [AssignHiddenCustomer::make()];
    }
}

class GuardedCustomerOrders extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->title('number')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::text('Number'), Field::belongsTo('Customer', 'customer', GuardedCustomers::class)->filterable()];
    }
}

class ListedCustomers extends Resource
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

class ListedCustomerOrders extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->title('number')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::text('Number'), Field::belongsTo('Customer', 'customer', ListedCustomers::class)->filterable()];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(HiddenCustomers::class, GuardedCustomers::class, ListedCustomers::class, HiddenCustomerOrders::class, GuardedCustomerOrders::class, ListedCustomerOrders::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    $this->order = Order::query()->orderBy('id')->firstOrFail();
    $this->customer = Customer::query()->findOrFail($this->order->customer_id);
    GuardedSetup::$viewable = $this->customer->email;
});

it('answers 403 on every picker and the relation filter when canSee hides the related Resource', function () {
    LatheTest::resource('hidden-customer-orders')->actionAssociatable('assign-hidden-customer', 'customer', ['q' => 'a'])->assertForbidden();
    $this->getJson('/lathe/api/resources/hidden-customer-orders/associatable/customer?q=a')->assertForbidden();
    $this->getJson("/lathe/api/resources/hidden-customer-orders/{$this->order->id}/customer/associatable?q=a")->assertForbidden();
    $this->getJson('/lathe/api/resources/hidden-customer-orders/filters/field_customer/options?q=a&page=1')->assertForbidden();
});

it('lets view decide the saved value when canSee hides the related Resource', function () {
    $this->getJson("/lathe/api/resources/hidden-customer-orders/{$this->order->id}/customer/associatable?current={$this->customer->id}")
        ->assertOk()
        ->assertJsonPath('items.0.display', $this->customer->name);
});

it('answers 403 to search and to the relation filter without viewAny on the related Resource', function () {
    $this->getJson('/lathe/api/resources/guarded-customer-orders/associatable/customer?q=a')->assertForbidden();
    $this->getJson("/lathe/api/resources/guarded-customer-orders/{$this->order->id}/customer/associatable?page=2")->assertForbidden();
    $this->getJson('/lathe/api/resources/guarded-customer-orders/filters/field_customer/options?q=a&page=1')->assertForbidden();
});

it('answers the saved value only when the user may view that one record', function () {
    $other = Customer::query()->whereKeyNot($this->customer->id)->orderBy('id')->firstOrFail();
    $uri = "/lathe/api/resources/guarded-customer-orders/{$this->order->id}/customer/associatable?current=";

    $this->getJson($uri.$this->customer->id)->assertOk()
        ->assertJsonPath('items.0.value', $this->customer->id)
        ->assertJsonPath('items.0.display', $this->customer->name)
        ->assertJsonPath('has_more', false);
    $this->getJson($uri.$other->id)->assertForbidden();
});

it('lists no relation filter options inline to a user who may not list the related records', function (string $resource) {
    config()->set('lathe.limits.filter_remote_threshold', 100000);

    $filter = collect($this->getJson("/lathe/api/resources/{$resource}/filters")->assertOk()->json('filters'))->firstWhere('key', 'field_customer');

    expect($filter)->toMatchArray(['remote' => true, 'options' => null]);
    $this->getJson("/lathe/api/resources/{$resource}/filters/field_customer/options?q=&page=1")->assertForbidden();
})->with(['canSee hides the Resource' => 'hidden-customer-orders', 'viewAny fails' => 'guarded-customer-orders']);

it('still lists a small related table inline to a user who may list it', function () {
    config()->set('lathe.limits.filter_remote_threshold', 100000);

    $filter = collect($this->getJson('/lathe/api/resources/listed-customer-orders/filters')->assertOk()->json('filters'))->firstWhere('key', 'field_customer');

    expect($filter['remote'])->toBeFalse()
        ->and($filter['options'])->not->toBeEmpty();
});
