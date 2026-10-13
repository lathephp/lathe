<?php

namespace Lathe\Tests\Feature\Lenses;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Lathe\Attributes\Lens\PerPage;
use Lathe\Attributes\Lens\Polling;
use Lathe\Attributes\Lens\Search;
use Lathe\Attributes\Lens\ShowPollingToggle;
use Lathe\Field\Field;
use Lathe\Filters\Filter;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Lenses\Lens;
use Lathe\Menu\MenuItem;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Lathe\Lenses\PendingOrders;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class LensGizmo extends Model
{
    protected $table = 'lens_gizmos';

    protected $guarded = [];

    public $timestamps = false;

    protected static function booted(): void
    {
        static::addGlobalScope('visible', fn (Builder $query) => $query->where('lens_gizmos.hidden', false));
    }
}

class GizmoStatus extends Filter
{
    public function name(): string
    {
        return 'Status';
    }

    public function key(): string
    {
        return 'status';
    }

    public function options(LatheRequest $request): array
    {
        return ['Open' => 'open', 'Closed' => 'closed'];
    }

    public function apply(LatheRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where($query->qualifyColumn('status'), $value);
    }
}

class AllGizmos extends Lens
{
    public function fields(LatheRequest $request): array
    {
        return [Field::make('Name')];
    }

    public static function query(LatheRequest $request, Builder $query): Builder
    {
        return $query;
    }
}

#[Search(['name'])]
class JoinedGizmos extends Lens
{
    public function fields(LatheRequest $request): array
    {
        return [Field::make('Name'), Field::make('Status')->sortable()];
    }

    public static function query(LatheRequest $request, Builder $query): Builder
    {
        return $query->join('lens_gizmo_notes', 'lens_gizmo_notes.gizmo_id', '=', 'lens_gizmos.id')
            ->select('lens_gizmos.*');
    }
}

class SelfFilteringGizmos extends Lens
{
    public function fields(LatheRequest $request): array
    {
        return [Field::make('Name'), Field::make('Status')->sortable()];
    }

    public function filters(LatheRequest $request): array
    {
        return [new GizmoStatus];
    }

    public static function query(LatheRequest $request, Builder $query): Builder
    {
        return static::withOrdering($request, static::withFilters($request, $query));
    }
}

class FixedCountGizmos extends AllGizmos
{
    public static function countQuery(LatheRequest $request, Builder $query): Builder|int|null
    {
        return 42;
    }
}

class NarrowCountGizmos extends AllGizmos
{
    public static function countQuery(LatheRequest $request, Builder $query): Builder|int|null
    {
        return $query->where('lens_gizmos.status', 'closed');
    }
}

#[Polling(30)]
#[ShowPollingToggle]
#[PerPage([5, 10])]
#[Search(['name'])]
class PolledGizmos extends AllGizmos
{
    //
}

class Gizmo extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(LensGizmo::class)->title('name')->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::make('Name')->sortable()];
    }

    public function lenses(LatheRequest $request): array
    {
        return [
            new AllGizmos,
            new JoinedGizmos,
            new SelfFilteringGizmos,
            new FixedCountGizmos,
            new NarrowCountGizmos,
        ];
    }
}

class HiddenGizmos extends AllGizmos
{
    //
}

class HidingGizmo extends Gizmo
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canonical(false);
    }

    public static function uriKey(): string
    {
        return 'hiding-gizmos';
    }

    public function lenses(LatheRequest $request): array
    {
        return [(new HiddenGizmos)->canSee(fn () => false)];
    }
}

class NoLensPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function viewLens(User $user, string $lens): bool
    {
        return false;
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => $user->email === 'admin@lathephp.com');

    Schema::create('lens_gizmos', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('status');
        $table->boolean('hidden');
    });
    Schema::create('lens_gizmo_notes', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('gizmo_id');
        $table->string('status');
    });

    // Rows 1 to 3 are hidden by the global scope; statuses alternate.
    foreach (range(1, 10) as $id) {
        DB::table('lens_gizmos')->insert(['id' => $id, 'name' => "Gizmo {$id}", 'status' => $id % 2 === 0 ? 'open' : 'closed', 'hidden' => $id <= 3]);
        DB::table('lens_gizmo_notes')->insert(['id' => 100 + $id, 'gizmo_id' => $id, 'status' => 'noted']);
    }

    Lathe::registerResources(Gizmo::class, HidingGizmo::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

/**
 * @return list<int>
 */
function pendingOrderIds(): array
{
    return Order::where('status', 'pending')->orderByDesc('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
}

/**
 * The SQL each statement of a request ran.
 *
 * @return list<string>
 */
function lensSqlDuring(Closure $request): array
{
    $sql = [];
    DB::listen(function ($event) use (&$sql) {
        $sql[] = str_replace(['"', '`'], '', $event->sql);
    });
    $request();

    return $sql;
}

it('shows the Pending Orders lens page with only pending rows and the lens columns', function () {
    $this->get('/lathe/resources/orders/lens/pending-orders?per_page=100')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Resources/Index', false)
            ->where('table.columns', fn ($columns) => collect($columns)->pluck('label')->all() === ['Number', 'Customer', 'Total', 'Placed'])
            ->where('table.rows', fn ($rows) => collect($rows)->pluck('id')->all() === array_slice(pendingOrderIds(), 0, 100))
            ->where('lens', ['uriKey' => 'pending-orders', 'name' => 'Pending Orders'])
            ->where('lenses', [['uriKey' => 'pending-orders', 'name' => 'Pending Orders']]));
});

it('lists only pending orders from the lens API and counts them', function () {
    $json = $this->getJson('/lathe/api/resources/orders/lenses/pending-orders?per_page=100')->assertOk()->json();

    expect(array_column($json['table']['rows'], 'id'))->toBe(array_slice(pendingOrderIds(), 0, 100))
        ->and(array_column($json['table']['columns'], 'label'))->toBe(['Number', 'Customer', 'Total', 'Placed'])
        ->and($json['table']['sortKeys'])->toBe(['total_cents', 'placed_at']);

    $this->getJson('/lathe/api/resources/orders/lenses/pending-orders/count')
        ->assertOk()
        ->assertJsonPath('value', count(pendingOrderIds()));

    expect(count(pendingOrderIds()))->toBeGreaterThan(100);
});

it('offers the lens filters and filters like the index', function () {
    $this->getJson('/lathe/api/resources/orders/lenses/pending-orders/filters')
        ->assertOk()
        ->assertJsonPath('filters.*.key', ['status', 'customer']);

    $lens = $this->getJson('/lathe/api/resources/orders/lenses/pending-orders?filter[status]=pending&per_page=100')->assertOk()->json('table.rows.*.id');
    $index = $this->getJson('/lathe/api/resources/orders?filter[status]=pending&per_page=100')->assertOk()->json('table.rows.*.id');

    expect($lens)->toBe($index)->toBe(array_slice(pendingOrderIds(), 0, 100));
    $this->getJson('/lathe/api/resources/orders/lenses/pending-orders?filter[field_total_cents][min]=1')->assertStatus(422);
});

it('searches a joined lens by its own columns', function () {
    $this->getJson('/lathe/api/resources/gizmos/lenses/joined-gizmos?q=Gizmo 1')
        ->assertOk()
        ->assertJsonPath('table.rows.*.id', [10]);
});

it('never shows a row the global scope hides', function () {
    $json = $this->getJson('/lathe/api/resources/gizmos/lenses/all-gizmos')->assertOk()->json();

    expect(array_column($json['table']['rows'], 'id'))->toBe([10, 9, 8, 7, 6, 5, 4]);
    $this->getJson('/lathe/api/resources/gizmos/lenses/all-gizmos/count')->assertJsonPath('value', 7);
});

it('sorts a joined lens by a column both tables share', function () {
    $sql = lensSqlDuring(fn () => $this->getJson('/lathe/api/resources/gizmos/lenses/joined-gizmos?sort=status')
        ->assertOk()
        ->assertJsonPath('table.rows.*.id', [5, 7, 9, 4, 6, 8, 10]));

    expect(implode("\n", $sql))->toContain('order by lens_gizmos.status asc, lens_gizmos.id asc');
});

it('applies withFilters and withOrdering called inside query() once', function () {
    $sql = lensSqlDuring(fn () => $this->getJson('/lathe/api/resources/gizmos/lenses/self-filtering-gizmos?filter[status]=open&sort=-status')
        ->assertOk()
        ->assertJsonPath('table.rows.*.id', [10, 8, 6, 4]));

    $rows = collect($sql)->first(fn (string $query) => str_contains($query, 'limit'));

    expect(substr_count((string) $rows, 'lens_gizmos.status = ?'))->toBe(1)
        ->and(substr_count((string) $rows, 'order by'))->toBe(1)
        ->and(substr_count((string) $rows, 'lens_gizmos.status desc'))->toBe(1);
});

it('lens_sort_rejects_unknown_column', function (string $sort) {
    $this->getJson("/lathe/api/resources/orders/lenses/pending-orders?sort={$sort}")->assertStatus(422);
    $this->get("/lathe/resources/orders/lens/pending-orders?sort={$sort}")->assertStatus(422);
})->with(['nope', 'number'])->group('regression');

it('sorts a lens by its sortable fields', function () {
    $rows = $this->getJson('/lathe/api/resources/orders/lenses/pending-orders?sort=-total_cents')->assertOk()->json('table.rows');

    expect($rows[0]['id'])->toBe((int) Order::where('status', 'pending')->orderByDesc('total_cents')->orderByDesc('id')->value('id'));
});

it('counts with countQuery() when the lens declares one', function (string $lens, int $count) {
    $this->getJson("/lathe/api/resources/gizmos/lenses/{$lens}/count")->assertOk()->assertJsonPath('value', $count);
})->with([
    ['fixed-count-gizmos', 42],
    ['narrow-count-gizmos', 3],
]);

it('reads polling, per page and search from the lens attributes', function () {
    $plain = new AllGizmos;
    $polled = new PolledGizmos;

    expect([$plain->polling(), $plain->pollingInterval(), $plain->showPollingToggle(), AllGizmos::perPageOptions(), AllGizmos::searchable(), $plain->searchableColumns()])
        ->toBe([false, 15, false, null, false, []])
        ->and([$polled->polling(), $polled->pollingInterval(), $polled->showPollingToggle(), PolledGizmos::perPageOptions(), PolledGizmos::searchable(), $polled->searchableColumns()])
        ->toBe([true, 30, true, [5, 10], true, ['name']]);
});

it('names a lens after its class', function () {
    expect([(new PendingOrders)->name(), (new PendingOrders)->uriKey()])->toBe(['Pending Orders', 'pending-orders']);
});

it('hidden_lens_endpoints_return_403', function (string $denial) {
    [$resource, $lens] = $denial === 'canSee' ? ['hiding-gizmos', 'hidden-gizmos'] : ['orders', 'pending-orders'];

    if ($denial === 'viewLens') {
        Gate::policy(Order::class, NoLensPolicy::class);
    }

    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn (Route $route) => preg_match('#/lens(es)?/\{lens\}#', $route->uri()) === 1);

    expect($routes->count())->toBeGreaterThanOrEqual(4);

    foreach ($routes as $route) {
        $uri = '/'.str_replace(['{resource}', '{lens}'], [$resource, $lens], $route->uri());

        $this->json($route->methods()[0], $uri)->assertForbidden();
    }
})->with(['canSee', 'viewLens'])->group('regression');

it('answers 404 for an unknown lens', function (string $uri) {
    $this->getJson($uri)->assertNotFound();
})->with([
    '/lathe/api/resources/orders/lenses/nope',
    '/lathe/api/resources/orders/lenses/nope/count',
    '/lathe/api/resources/orders/lenses/nope/filters',
    '/lathe/resources/orders/lens/nope',
]);

it('menu_item_links_to_a_lens', function () {
    expect(MenuItem::lens('orders', 'pending-orders')->jsonSerialize())
        ->toBe(['label' => 'Pending Orders', 'href' => '/lathe/resources/orders/lens/pending-orders', 'icon' => null]);
})->group('regression');
