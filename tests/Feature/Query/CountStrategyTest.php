<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Lathe\Enums\PaginationType;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\CountStrategy;
use Lathe\Query\ResourceQuery;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;

class CountedItem extends Model
{
    protected $table = 'counted_items';

    protected $guarded = [];
}

class ScopedCountedItem extends CountedItem
{
    protected static function booted(): void
    {
        static::addGlobalScope('visible', fn (Builder $query) => $query->where('visible', true));
    }
}

abstract class CountedResource extends Resource
{
    protected static CountStrategy $strategy = CountStrategy::Capped;

    protected static ?int $cap = null;

    /** @var class-string<Model> */
    protected static string $countedModel = CountedItem::class;

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(static::$countedModel)->countStrategy(static::$strategy, static::$cap);
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }
}

class ExactCounted extends CountedResource
{
    protected static CountStrategy $strategy = CountStrategy::Exact;
}

class SmallExactCounted extends CountedResource
{
    protected static CountStrategy $strategy = CountStrategy::Exact;

    protected static ?int $cap = 500;
}

class EstimatedCounted extends CountedResource
{
    protected static CountStrategy $strategy = CountStrategy::Estimated;
}

class ScopedEstimatedCounted extends CountedResource
{
    protected static CountStrategy $strategy = CountStrategy::Estimated;

    protected static string $countedModel = ScopedCountedItem::class;
}

class NarrowedEstimatedCounted extends CountedResource
{
    protected static CountStrategy $strategy = CountStrategy::Estimated;

    public static function indexQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query->where('visible', true);
    }
}

class UncountedCounted extends CountedResource
{
    protected static CountStrategy $strategy = CountStrategy::None;
}

beforeEach(function () {
    Schema::create('counted_items', function (Blueprint $table) {
        $table->id();
        $table->boolean('visible')->default(true);
    });
    DB::table('counted_items')->insert(array_fill(0, 12, ['visible' => true]));
});

it('counts every row with exact and says so', function () {
    config(['lathe.limits.count_cap' => 5]);
    $request = LatheRequest::createFrom(Request::create('/lathe/api/resources/counted'));
    $queries = [];
    DB::listen(function ($event) use (&$queries) {
        $queries[] = $event->sql;
    });

    $count = (new ResourceQuery(ExactCounted::make(), $request))->count();

    expect($count->value)->toBe(12)
        ->and($count->exact)->toBeTrue()
        ->and($count->capped)->toBeFalse()
        ->and($count->estimated)->toBeFalse()
        ->and($queries)->toHaveCount(1)
        ->and(strtolower($queries[0]))->toContain('count(*)')->not->toContain('limit');
});

it('estimates the rows of an unnarrowed table', function () {
    config(['lathe.limits.count_cap' => 5]);
    $request = LatheRequest::createFrom(Request::create('/lathe/api/resources/counted'));

    $count = (new ResourceQuery(EstimatedCounted::make(), $request))->count();

    // SQLite has no estimate, so it falls back to the capped count.
    if (DB::connection()->getDriverName() === 'sqlite') {
        expect($count->value)->toBe(5)->and($count->capped)->toBeTrue()->and($count->estimated)->toBeFalse();
    } else {
        expect($count->estimated)->toBeTrue()->and($count->exact)->toBeFalse()->and($count->capped)->toBeFalse();
    }
});

it('falls back to the capped count when the query is narrowed', function (string $resource, string $query) {
    config(['lathe.limits.count_cap' => 5]);
    $request = LatheRequest::createFrom(Request::create('/lathe/api/resources/counted?'.$query));

    $count = (new ResourceQuery($resource::make(), $request))->count();

    expect($count->estimated)->toBeFalse()
        ->and($count->capped)->toBeTrue()
        ->and($count->value)->toBe(5);
})->with([
    'a global scope' => [ScopedEstimatedCounted::class, ''],
    'an indexQuery where' => [NarrowedEstimatedCounted::class, ''],
]);

it('runs no count query with none and pages simply', function () {
    $request = LatheRequest::createFrom(Request::create('/lathe/api/resources/counted'));
    $queries = [];
    DB::listen(function ($event) use (&$queries) {
        $queries[] = $event->sql;
    });

    $count = (new ResourceQuery(UncountedCounted::make(), $request))->count();

    expect($count)->toBeNull()
        ->and($queries)->toBe([])
        ->and(UncountedCounted::make()->definition()->getPaginationType())->toBe(PaginationType::Simple)
        ->and(UncountedCounted::make()->definition()->options()['paginationType'])->toBe('simple');
});

it('caps paging at the cap the Resource sets', function () {
    $query = fn (string $page) => new ResourceQuery(SmallExactCounted::make(), LatheRequest::createFrom(Request::create('/lathe/api/resources/counted?page='.$page)));

    expect($query('20')->page())->toBe([25, 20]);
    expect(fn () => $query('21')->page())->toThrow(ValidationException::class);
});
