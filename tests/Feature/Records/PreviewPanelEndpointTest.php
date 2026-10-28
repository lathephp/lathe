<?php

namespace Lathe\Tests\Feature\Records;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Testing\LatheTest;
use Workbench\App\Models\Order as OrderModel;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

/** Lists two fields for the preview panel, neither of which calls showOnPreview(). */
class PanelListedOrder extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(OrderModel::class)->title('number')->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::make('Number'), Field::make('Status'), Field::make('Total', 'total_cents')];
    }

    public function fieldsForPreview(LatheRequest $request): array
    {
        return [Field::make('Number'), Field::make('Total', 'total_cents')];
    }
}

/** Hides and refuses fields in the default mode. */
class PanelGuardedOrder extends PanelListedOrder
{
    public function fieldsForPreview(LatheRequest $request): array
    {
        return [];
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Number'),
            Field::make('Status')->hideFromPreview(),
            Field::make('Total', 'total_cents')->canSee(fn () => false),
            Field::make('Placed', 'placed_at')->hideFromPreview(fn () => false),
        ];
    }
}

/** Hides and refuses fields in the explicit mode. */
class PanelGuardedListedOrder extends PanelListedOrder
{
    public function fieldsForPreview(LatheRequest $request): array
    {
        return [
            Field::make('Number'),
            Field::make('Status')->hideFromPreview(),
            Field::make('Total', 'total_cents')->canSee(fn () => false),
        ];
    }
}

/** Lets the panel list orders but never view one. */
class PanelDenyingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, OrderModel $order): bool
    {
        return false;
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(PanelListedOrder::class, PanelGuardedOrder::class, PanelGuardedListedOrder::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('previews an order with its Detail fields minus the relation lists', function () {
    $keys = array_column(LatheTest::resource('orders')->preview(12)->assertOk()->json('fields'), 'key');

    // Order.php: customer, status, placed, number, created (admin only), total on Detail;
    // the items HasMany and the action events panel are relation lists and drop out.
    expect($keys)->toBe(['customer', 'status', 'placed_at', 'number', 'created_at', 'total_cents'])
        ->and($keys)->not->toContain('items');
});

it('previews exactly the fields fieldsForPreview returns, in order', function () {
    expect(array_column(LatheTest::resource('panel-listed-orders')->preview(12)->assertOk()->json('fields'), 'key'))->toBe(['number', 'total_cents']);
});

it('drops a field hidden from the preview or refused by canSee, in both modes', function () {
    expect(array_column(LatheTest::resource('panel-guarded-orders')->preview(12)->assertOk()->json('fields'), 'key'))->toBe(['number', 'placed_at'])
        ->and(array_column(LatheTest::resource('panel-guarded-listed-orders')->preview(12)->assertOk()->json('fields'), 'key'))->toBe(['number']);
});

it('carries the detail actions and the abilities of the record', function () {
    $json = LatheTest::resource('orders')->preview(12)->assertOk()->json();
    $detail = LatheTest::resource('orders')->show(12)->assertOk()->json('props');

    expect(array_keys($json))->toBe(['id', 'title', 'subtitle', 'avatar', 'fields', 'abilities', 'actions'])
        ->and($json['actions'])->toBe($detail['actions'])
        ->and($json['actions'])->not->toBeEmpty()
        ->and($json['abilities']['update'])->toBeTrue()
        ->and($json['abilities'])->toBe($detail['record']['abilities']);
});

it('answers 404 for a missing or soft-deleted record and 403 without the view ability', function () {
    $trashed = Post::onlyTrashed()->orderBy('id')->firstOrFail();

    LatheTest::resource('orders')->preview(99999999)->assertNotFound();
    LatheTest::resource('posts')->preview($trashed->id)->assertNotFound();

    Gate::policy(OrderModel::class, PanelDenyingPolicy::class);
    LatheTest::resource('orders')->preview(12)->assertForbidden();
});

it('builds a preview with the record, its customer and nothing else: no list, count or action query', function () {
    $queries = [];
    DB::listen(function ($event) use (&$queries) {
        $queries[] = $event->sql;
    });

    LatheTest::resource('orders')->preview(12)->assertOk();

    $sql = implode("\n", $queries);

    expect($queries)->toHaveCount(2)
        ->and(array_values(array_filter($queries, fn (string $q) => preg_match('/from ["`]?orders["`]?(\s|$)/i', $q) === 1)))->toHaveCount(1)
        ->and($sql)->not->toContain('count(')
        ->and($sql)->not->toContain('offset');
});
