<?php

namespace Lathe\Tests\Feature\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Lathe\Field\Field;
use Lathe\Field\Relations\RelationField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Tests\Fixtures\Models\Gadget;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\Product;
use Workbench\App\Models\Tag;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class HookSetup
{
    public static ?string $trashed = null;
}

class HookOwner extends Model
{
    protected $table = 'hook_owners';

    /**
     * @return BelongsTo<Gadget, $this>
     */
    public function gadget(): BelongsTo
    {
        return $this->belongsTo(Gadget::class);
    }
}

class HookGadget extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Gadget::class)->title('name')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::text('Name')];
    }
}

class HookOwnerResource extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(HookOwner::class)->title('id')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        $gadget = Field::belongsTo('Gadget')->resource(HookGadget::class);

        return [match (HookSetup::$trashed) {
            'with' => $gadget->withTrashed(),
            'without' => $gadget->withTrashed()->withoutTrashed(),
            default => $gadget,
        }];
    }
}

class HookCustomer extends Resource
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

class HookOrder extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->title('number')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::belongsTo('Customer')->resource(HookCustomer::class)
                ->relatableQuery(fn (LatheRequest $request, Builder $query) => $query->where('country', 'US')),
        ];
    }
}

class HookTag extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Tag::class)->title('name')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::text('Name')];
    }

    public static function relatableQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query->whereKey(3);
    }
}

class HookProduct extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Product::class)->title('name')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::morphToMany('Tags')->resource(HookTag::class)];
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function relatableTags(LatheRequest $request, Builder $query, RelationField $field): Builder
    {
        return $query->whereKey(2);
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Schema::create('invoices', function (Blueprint $table) {
        $table->id();
        $table->string('code');
        $table->timestamps();
    });
    Schema::create('gadgets', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->unsignedInteger('team_id');
        $table->foreignId('invoice_id')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
    Schema::create('hook_owners', function (Blueprint $table) {
        $table->id();
        $table->foreignId('gadget_id')->nullable();
    });
    DB::table('hook_owners')->insert(['gadget_id' => null]);
    DB::table('gadgets')->insert([
        ['id' => 1, 'name' => 'Visible', 'team_id' => 1, 'deleted_at' => null],
        ['id' => 2, 'name' => 'Other team', 'team_id' => 2, 'deleted_at' => null],
        ['id' => 3, 'name' => 'Trashed', 'team_id' => 1, 'deleted_at' => now()],
    ]);
    HookSetup::$trashed = null;
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(HookGadget::class, HookOwnerResource::class, HookCustomer::class, HookOrder::class, HookTag::class, HookProduct::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('narrows the lookup with the field relatableQuery', function () {
    $items = $this->getJson('/lathe/api/resources/hook-orders/associatable/customer?q=a')->assertOk()->json('items');
    $countries = Customer::whereKey(array_column($items, 'value'))->pluck('country')->unique()->all();

    expect($items)->not->toBeEmpty()
        ->and($countries)->toBe(['US']);
});

it('lets the parent relatable method replace the related generic hook', function () {
    $this->getJson('/lathe/api/resources/hook-products/1/tags/associatable')
        ->assertOk()
        ->assertJsonPath('items.*.value', [2]);
});

it('always applies the related global scopes', function () {
    $this->getJson('/lathe/api/resources/hook-owner-resources/associatable/gadget')
        ->assertOk()
        ->assertJsonPath('items.*.value', [1]);
    $this->getJson('/lathe/api/resources/hook-owner-resources/associatable/gadget?current=2')->assertNotFound();
});

it('returns the one record for current and ignores q and page', function () {
    $this->getJson('/lathe/api/resources/hook-owner-resources/associatable/gadget?current=1&q=zzz&page=4')
        ->assertOk()
        ->assertExactJson(['items' => [['value' => 1, 'display' => 'Visible']], 'has_more' => false]);
    $this->getJson('/lathe/api/resources/orders/12/customer/associatable?current=12')
        ->assertOk()
        ->assertJsonPath('items.*.value', [12])
        ->assertJsonPath('has_more', false);
});

it('answers 404 for a current key the field hook hides', function () {
    $other = Customer::where('country', '!=', 'US')->firstOrFail()->getKey();

    $this->getJson('/lathe/api/resources/hook-orders/associatable/customer?current='.$other)->assertNotFound();
    $this->getJson('/lathe/api/resources/hook-orders/12/customer/associatable?current='.$other)->assertNotFound();
});

it('excludes trashed rows unless the field allows them and the request asks', function (?string $setup, string $query, array $expected) {
    HookSetup::$trashed = $setup;

    $this->getJson('/lathe/api/resources/hook-owner-resources/associatable/gadget'.$query)
        ->assertOk()
        ->assertJsonPath('items.*.value', $expected);
})->with([
    'no parameter' => [null, '', [1]],
    'parameter, field refuses' => [null, '?with_trashed=1', [1]],
    'parameter, field allows' => ['with', '?with_trashed=1', [3, 1]],
    'index trashed parameter' => [null, '?trashed=with', [1]],
    'field allows, no parameter' => ['with', '', [1]],
    'parameter after withoutTrashed' => ['without', '?with_trashed=1', [1]],
]);

it('finds a trashed row by current only when trashed rows are allowed', function () {
    $this->getJson('/lathe/api/resources/hook-owner-resources/associatable/gadget?current=3')->assertNotFound();

    HookSetup::$trashed = 'with';

    $this->getJson('/lathe/api/resources/hook-owner-resources/associatable/gadget?current=3&with_trashed=1')
        ->assertOk()
        ->assertJsonPath('items.*.value', [3]);
});
