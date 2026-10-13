<?php

namespace Lathe\Tests\Feature\Records;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use InvalidArgumentException;
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

/** What the create hook saw. */
class ReplicateLog
{
    /** @var list<string> */
    public static array $created = [];
}

class ReplicableOrder extends Resource
{
    public static function uriKey(): string
    {
        return 'replicable-orders';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->title('number')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::make('Number'), Field::make('Status'), Field::make('Total', 'total_cents')];
    }

    public static function replicateExcept(): array
    {
        return ['number'];
    }

    public static function replicateQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query->where('status', '!=', 'cancelled');
    }
}

class ReplicableCustomer extends Resource
{
    public static function uriKey(): string
    {
        return 'replicable-customers';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Customer::class)->title('name')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::make('Name')->rules('required'), Field::make('Email')->rules('required'), Field::make('Country')];
    }

    public static function beforeCreate(LatheRequest $request, Model $model): void
    {
        ReplicateLog::$created[] = (string) $request->input('name');
    }
}

/** Users with an avatar that keeps its name and size. */
class ReplicablePerson extends Resource
{
    public static function uriKey(): string
    {
        return 'replicable-people';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(User::class)->title('name')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Name'),
            Field::make('Email'),
            Field::file('Avatar', 'avatar')->prunable()->storeOriginalName('avatar_name')->storeSize('avatar_size'),
        ];
    }

    public static function defaultAttributes(LatheRequest $request): array
    {
        return ['password' => 'secret-password'];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(ReplicableOrder::class, ReplicableCustomer::class, ReplicablePerson::class);
    Storage::fake('public');
    ReplicateLog::$created = [];
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('fills the replicate form from the source and empties a replicateExcept column', function () {
    $order = Order::query()->findOrFail(12);
    $orders = Order::query()->count();

    $json = LatheTest::resource('replicable-orders')->replicateFields(12)->assertOk()->json();

    expect(array_keys($json))->toBe(['fields', 'defaults', 'layout', 'borderless'])
        ->and(array_column($json['fields'], 'key'))->toBe(['number', 'status', 'total_cents'])
        ->and(array_column($json['fields'], 'value'))->toBe([null, $order->status, $order->total_cents])
        ->and(Order::query()->count())->toBe($orders);
});

it('renders the replicate page as a create form for the source', function () {
    $this->get('/lathe/resources/replicable-orders/12/replicate')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Resources/Form', false)
            ->where('mode', 'replicate')
            ->where('id', 12)
            ->where('submitLabel', 'Create Replicable Order')
            ->where('form.fields.0.value', null)
            ->where('breadcrumbs.2.href', '/lathe/resources/replicable-orders/12')
            ->where('breadcrumbs.3', ['label' => 'Replicate', 'href' => null]));
});

it('checks replicate before it renders the page', function () {
    $this->get('/lathe/resources/replicable-orders/99999999/replicate')
        ->assertNotFound()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Error', false)->where('status', 404)->where('shell', true));

    $this->actingAs(User::where('email', 'editor@lathephp.com')->firstOrFail());

    $this->get('/lathe/resources/orders/12/replicate')
        ->assertForbidden()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Error', false)->where('status', 403)->where('shell', true));
});

it('creates a new record from a replica through the normal create', function () {
    $customer = Customer::query()->firstOrFail();
    $fields = LatheTest::resource('replicable-customers')->replicateFields($customer->id)->assertOk()->json('fields');
    $values = array_column($fields, 'value', 'key');

    expect($values)->toBe(['name' => $customer->name, 'email' => $customer->email, 'country' => $customer->country]);

    LatheTest::resource('replicable-customers')->store([...$values, 'name' => ''])->assertUnprocessable();

    $before = Customer::query()->count();
    $response = LatheTest::resource('replicable-customers')->store([...$values, 'name' => 'Copy of '.$customer->name, 'email' => 'copy@example.com'])->assertCreated();
    $copy = Customer::query()->where('email', 'copy@example.com')->firstOrFail();

    expect(Customer::query()->count())->toBe($before + 1)
        ->and($copy->getKey())->not->toBe($customer->getKey())
        ->and(ReplicateLog::$created)->toBe(['Copy of '.$customer->name])
        ->and($response->json('redirect'))->toBe('replicable-customers/'.$copy->getKey());
});

it('finds the source through replicateQuery', function () {
    Order::query()->whereKey(12)->update(['status' => 'cancelled']);

    LatheTest::resource('replicable-orders')->replicateFields(12)->assertNotFound();
    LatheTest::resource('replicable-orders')->replicateFields(99999999)->assertNotFound();
});

it('refuses the replicate fields to a user the policy denies', function () {
    $this->actingAs(User::where('email', 'editor@lathephp.com')->firstOrFail());

    LatheTest::resource('orders')->replicateFields(12)->assertForbidden();
});

it('throws for a model that does not exist', function () {
    ReplicableOrder::replicate(new Order);
})->throws(InvalidArgumentException::class);

it('copies the attributes of a stored model except the ones named', function () {
    $order = Order::query()->findOrFail(12);

    $replica = ReplicableOrder::replicate($order);

    expect($replica->exists)->toBeFalse()
        ->and($replica->getAttribute('number'))->toBeNull()
        ->and($replica->getAttribute('status'))->toBe($order->status);
});

it('leaves every column of a file field empty in the copy and never deletes the source file', function () {
    Storage::disk('public')->put('avatars/source.png', 'source bytes');
    $source = User::query()->where('email', 'editor@lathephp.com')->firstOrFail();
    $source->forceFill(['avatar' => 'avatars/source.png', 'avatar_name' => 'me.png', 'avatar_size' => 12])->save();

    $replica = ReplicablePerson::replicate($source);
    $form = LatheTest::resource('replicable-people')->replicateFields($source->getKey())->assertOk()->json('fields');

    expect($replica->getAttribute('avatar'))->toBeNull()
        ->and($replica->getAttribute('avatar_name'))->toBeNull()
        ->and($replica->getAttribute('avatar_size'))->toBeNull()
        ->and($replica->getAttribute('name'))->toBe($source->name)
        ->and($source->fresh()?->avatar)->toBe('avatars/source.png')
        ->and(array_column($form, 'value', 'key')['name'])->toBe($source->name);

    $id = LatheTest::resource('replicable-people')->store(['name' => 'Copy', 'email' => 'copy@example.com'])->assertCreated()->json('id');
    LatheTest::resource('replicable-people')->delete($id)->assertOk();

    expect(Storage::disk('public')->exists('avatars/source.png'))->toBeTrue();
});
