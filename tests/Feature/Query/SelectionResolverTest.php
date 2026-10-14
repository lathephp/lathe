<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Query\Selection;
use Lathe\Query\SelectionResolver;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Tests\Fixtures\Models\StringThing as StringThingModel;
use Lathe\Tests\Fixtures\Models\UlidThing as UlidThingModel;
use Lathe\Tests\Fixtures\Models\UuidThing as UuidThingModel;
use Lathe\Tests\Fixtures\Resources\StringThing;
use Lathe\Tests\Fixtures\Resources\UlidThing;
use Lathe\Tests\Fixtures\Resources\UuidThing;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Workbench\App\Lathe\Resources\Order as OrderResource;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class PickableItem extends Model
{
    protected $table = 'pickable_items';

    protected $guarded = [];

    public $timestamps = false;

    protected static function booted(): void
    {
        static::addGlobalScope('visible', fn (Builder $query) => $query->where('visible', true));
    }
}

class PickableItemResource extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(PickableItem::class)->title('id')->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    app(DemoSeeder::class)->forOrders(90)->run();
    Gate::define('viewLathe', fn (User $user) => $user->email === 'admin@lathephp.com');
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

function resolverRequest(): LatheRequest
{
    // The query string stays empty: the resolver reads the selection, never the URL.
    return LatheRequest::createFrom(Request::create('/lathe/api/resources/orders/action?q=ignored&filter[status]=ignored', 'POST'));
}

/**
 * @param  array<string, mixed>  $selection
 */
function resolverFor(Resource $resource, array $selection): SelectionResolver
{
    return new SelectionResolver($resource, resolverRequest(), Selection::fromArray($selection));
}

/**
 * The keys of the rows one index request shows, in key order.
 *
 * @return list<int|string>
 */
function indexKeys(string $url): array
{
    $keys = array_column(test()->getJson($url.(str_contains($url, '?') ? '&' : '?').'per_page=100')->assertOk()->json('table.rows'), 'id');
    sort($keys);

    return $keys;
}

/**
 * @return list<int|string>
 */
function resolvedKeys(SelectionResolver $resolver): array
{
    $keys = $resolver->lazy()->map(fn (Model $row) => $row->getKey())->all();
    sort($keys);

    return array_values($keys);
}

it('resolves mode all to the rows the index shows for that state', function (array $state, string $url) {
    $number = substr(Order::query()->orderBy('id')->firstOrFail()->number, 0, 8);
    array_walk_recursive($state, function (mixed &$value) use ($number) {
        $value = $value === 'NUMBER' ? $number : $value;
    });
    $expected = indexKeys(str_replace('NUMBER', $number, $url));

    $resolver = resolverFor(OrderResource::make(), ['mode' => 'all', ...$state]);

    expect($expected)->not->toBeEmpty()
        ->and(resolvedKeys($resolver))->toBe($expected);
})->with([
    'search' => [['q' => 'NUMBER'], '/lathe/api/resources/orders?q=NUMBER'],
    'filter' => [['filters' => ['status' => 'paid']], '/lathe/api/resources/orders?filter[status]=paid'],
    'tab' => [['tab' => 'pending'], '/lathe/api/resources/orders?tab=pending'],
    'trashed' => [['trashed' => 'with'], '/lathe/api/resources/orders?trashed=with'],
    'lens' => [['lens' => 'pending-orders', 'filters' => ['status' => 'pending']], '/lathe/api/resources/orders/lenses/pending-orders?filter[status]=pending'],
    'everything' => [['q' => 'ORD', 'filters' => ['status' => 'paid'], 'tab' => 'paid'], '/lathe/api/resources/orders?q=ORD&filter[status]=paid&tab=paid'],
]);

it('narrows a state to fewer rows than the table holds', function () {
    $all = Order::query()->count();

    expect(resolverFor(OrderResource::make(), ['mode' => 'all', 'filters' => ['status' => 'paid']])->query()->count())
        ->toBeLessThan($all)->toBeGreaterThan(0);
});

it('leaves out the exclusions of mode all', function () {
    $expected = indexKeys('/lathe/api/resources/orders?filter[status]=paid');
    $excluded = [$expected[0], (string) $expected[2]];

    $resolver = resolverFor(OrderResource::make(), ['mode' => 'all', 'filters' => ['status' => 'paid'], 'exclusions' => $excluded]);

    expect(resolvedKeys($resolver))->toBe(array_values(array_diff($expected, [$expected[0], $expected[2]])));
});

it('resolves mode ids to the named rows inside the narrowed state', function () {
    $paid = indexKeys('/lathe/api/resources/orders?filter[status]=paid');
    $other = Order::query()->where('status', '!=', 'paid')->value('id');

    $resolver = resolverFor(OrderResource::make(), ['mode' => 'ids', 'ids' => [$paid[0], $paid[1], $other], 'filters' => ['status' => 'paid']]);

    expect(resolvedKeys($resolver))->toBe([$paid[0], $paid[1]]);
});

it('never includes a row a global scope hides', function () {
    Schema::create('pickable_items', function (Blueprint $table) {
        $table->id();
        $table->boolean('visible');
    });
    DB::table('pickable_items')->insert([['visible' => true], ['visible' => true], ['visible' => false], ['visible' => true]]);
    $resource = PickableItemResource::make();

    expect(resolvedKeys(resolverFor($resource, ['mode' => 'all'])))->toBe([1, 2, 4])
        ->and(resolvedKeys(resolverFor($resource, ['mode' => 'all', 'exclusions' => [2]])))->toBe([1, 4])
        ->and(resolvedKeys(resolverFor($resource, ['mode' => 'ids', 'ids' => [1, 3]])))->toBe([1]);
});

it('answers 422 for an unknown filter key', function () {
    $resolver = resolverFor(OrderResource::make(), ['mode' => 'all', 'filters' => ['nope' => 'x']]);

    expect(fn () => $resolver->query())->toThrow(ValidationException::class);
});

it('answers 404 for an unknown lens', function () {
    $resolver = resolverFor(OrderResource::make(), ['mode' => 'all', 'lens' => 'nope']);

    expect(fn () => $resolver->query())->toThrow(NotFoundHttpException::class);
});

it('answers 422 when mode all matches no row', function () {
    $resolver = resolverFor(OrderResource::make(), ['mode' => 'all', 'q' => 'zzzzqqqq']);

    try {
        $resolver->ensureNotEmpty();
        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $e) {
        expect($e->errors())->toBe(['selection' => ['Select at least one row.']]);
    }
});

it('answers 422 when mode ids names no row', function () {
    expect(fn () => resolverFor(OrderResource::make(), ['mode' => 'ids', 'ids' => []])->ensureNotEmpty())
        ->toThrow(ValidationException::class);
});

it('passes ensureNotEmpty when rows match', function () {
    resolverFor(OrderResource::make(), ['mode' => 'all'])->ensureNotEmpty();

    expect(true)->toBeTrue();
});

it('reads a large set in limited chunks and checks the threshold with a capped subselect', function () {
    Schema::create('pickable_items', function (Blueprint $table) {
        $table->id();
        $table->boolean('visible');
    });
    DB::table('pickable_items')->insert(array_fill(0, 450, ['visible' => true]));
    $resolver = resolverFor(PickableItemResource::make(), ['mode' => 'all']);
    $queries = [];
    DB::listen(function ($event) use (&$queries) {
        $queries[] = strtolower($event->sql);
    });

    $seen = $resolver->lazy(200)->count();
    $chunkReads = $queries;
    $queries = [];
    $over500 = $resolver->exceeds(500);
    $over100 = $resolver->exceeds(100);

    expect($seen)->toBe(450)
        ->and($chunkReads)->toHaveCount(3)
        ->and($chunkReads)->each->toContain('limit 200')
        ->and($over500)->toBeFalse()
        ->and($over100)->toBeTrue()
        ->and($queries[0])->toContain('limit 501')
        ->and($queries[1])->toContain('limit 101');
});

it('answers exceeds for mode ids from the length of the list', function () {
    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });
    $resolver = resolverFor(OrderResource::make(), ['mode' => 'ids', 'ids' => [1, 2, 3]]);

    expect($resolver->exceeds(2))->toBeTrue()
        ->and($resolver->exceeds(3))->toBeFalse()
        ->and($queries)->toBe(0);
});

it('resolves UUID, ULID and string keys', function (string $resource, string $table, string $model, string $column, array $keys) {
    Schema::create($table, function (Blueprint $table) use ($column) {
        $table->{$column}('id')->primary();
        $table->string('name');
        $table->timestamps();
    });
    Lathe::registerResources($resource);
    foreach ($keys as $key) {
        $model::forceCreate(['id' => $key, 'name' => 'Thing']);
    }
    sort($keys);

    expect(resolvedKeys(resolverFor($resource::make(), ['mode' => 'ids', 'ids' => [$keys[0], $keys[2]]])))->toBe([$keys[0], $keys[2]])
        ->and(resolvedKeys(resolverFor($resource::make(), ['mode' => 'all', 'exclusions' => [$keys[1]]])))->toBe([$keys[0], $keys[2]]);
})->with([
    'uuid' => [UuidThing::class, 'uuid_things', UuidThingModel::class, 'uuid', ['9f1c2b3a-5d4e-4f60-8a7b-1c2d3e4f5a6b', '9f1c2b3b-5d4e-4f60-8a7b-1c2d3e4f5a6b', '9f1c2b3c-5d4e-4f60-8a7b-1c2d3e4f5a6b']],
    'ulid' => [UlidThing::class, 'ulid_things', UlidThingModel::class, 'ulid', ['01j9x7k2m3n4p5q6r7s8t9v0wx', '01j9x7k3m3n4p5q6r7s8t9v0wx', '01j9x7k4m3n4p5q6r7s8t9v0wx']],
    'string' => [StringThing::class, 'string_things', StringThingModel::class, 'string', ['sku-alpha-0001', 'sku-alpine-0001', 'sku-beta-0001']],
]);
