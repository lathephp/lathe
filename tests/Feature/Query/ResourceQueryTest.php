<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Tests\Fixtures\Models\Gadget as GadgetModel;
use Lathe\Tests\Fixtures\Resources\Gadget;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
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
    Gadget::$hooks = [];
});

function gadgetQuery(string $query = '', QueryContext $context = QueryContext::Index): ResourceQuery
{
    $request = LatheRequest::createFrom(Request::create('/lathe/api/resources/gadgets?'.$query));

    return new ResourceQuery(Gadget::make(), $request, $context);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function gadget(array $attributes = []): GadgetModel
{
    return GadgetModel::withoutGlobalScopes()->forceCreate([
        'name' => 'Gadget',
        'team_id' => 1,
        ...$attributes,
    ]);
}

it('calls the hook of its context and no other', function (QueryContext $context, string $hook) {
    gadgetQuery(context: $context)->scoped();

    expect(Gadget::$hooks)->toBe([$hook]);
})->with([
    [QueryContext::Index, 'indexQuery'],
    [QueryContext::Detail, 'detailQuery'],
    [QueryContext::Edit, 'editQuery'],
    [QueryContext::Search, 'indexQuery'],
    [QueryContext::Widget, 'indexQuery'],
    [QueryContext::Relatable, 'relatableQuery'],
    [QueryContext::Replicate, 'replicateQuery'],
]);

it('gives search and widget contexts their own hooks when a Resource defines them', function (QueryContext $context, string $hook) {
    $resource = new class extends Resource
    {
        /** @var list<string> */
        public static array $calls = [];

        public function fields(LatheRequest $request): array
        {
            return [];
        }

        protected function configure(ResourceDefinition $definition): ResourceDefinition
        {
            return $definition->model(GadgetModel::class);
        }

        public static function searchQuery(LatheRequest $request, Builder $query): Builder
        {
            self::$calls[] = 'searchQuery';

            return $query;
        }

        public static function widgetQuery(LatheRequest $request, Builder $query): Builder
        {
            self::$calls[] = 'widgetQuery';

            return $query;
        }
    };
    $resource::$calls = [];
    $request = LatheRequest::createFrom(Request::create('/lathe'));

    (new ResourceQuery($resource, $request, $context))->scoped();

    expect($resource::$calls)->toBe([$hook]);
})->with([
    [QueryContext::Search, 'searchQuery'],
    [QueryContext::Widget, 'widgetQuery'],
]);

it('declares scoutQuery with its two arguments', function () {
    $method = new ReflectionMethod(Resource::class, 'scoutQuery');

    expect($method->isStatic())->toBeTrue()
        ->and($method->getNumberOfParameters())->toBe(2);
});

it('keeps rows and counts inside the global scope', function () {
    gadget(['name' => 'Mine']);
    $other = gadget(['name' => 'Theirs', 'team_id' => 2]);

    expect(gadgetQuery()->rows()->pluck('name')->all())->toBe(['Mine'])
        ->and(gadgetQuery()->count()->value)->toBe(1)
        ->and(fn () => gadgetQuery(context: QueryContext::Detail)->find($other->id))->toThrow(NotFoundHttpException::class);
});

it('lifts one named scope for one call', function () {
    gadget(['team_id' => 2]);

    expect(gadgetQuery()->withoutScope('team')->count()->value)->toBe(1)
        ->and(gadgetQuery()->count()->value)->toBe(0);
});

it('keeps an indexQuery select in rows and count', function () {
    gadget(['name' => 'Selected']);
    $resource = new class extends Gadget
    {
        public static function indexQuery(LatheRequest $request, Builder $query): Builder
        {
            return $query->select(['id', 'name']);
        }
    };
    $request = LatheRequest::createFrom(Request::create('/lathe'));
    $query = new ResourceQuery($resource, $request);

    $queries = [];
    DB::listen(function ($event) use (&$queries) {
        $queries[] = $event->sql;
    });

    expect(array_keys($query->rows()->first()?->getAttributes() ?? []))->toBe(['id', 'name'])
        ->and($query->count()->value)->toBe(1)
        ->and($queries[1])->toContain(quotedSql('select "id", "name" from "gadgets"'));
});

it('returns live rows, then trashed and live, then only trashed', function () {
    gadget(['name' => 'Live']);
    gadget(['name' => 'Gone'])->delete();

    expect(gadgetQuery()->rows()->pluck('name')->all())->toBe(['Live'])
        ->and(gadgetQuery()->withTrashed()->rows()->pluck('name')->sort()->values()->all())->toBe(['Gone', 'Live'])
        ->and(gadgetQuery()->onlyTrashed()->rows()->pluck('name')->all())->toBe(['Gone'])
        ->and(gadgetQuery('trashed=only')->rows()->pluck('name')->all())->toBe(['Gone']);
});

it('orders rows by primary key, newest first, and eager-loads with()', function () {
    $first = gadget();
    $last = gadget();
    Model::preventLazyLoading();

    $rows = gadgetQuery()->rows();

    expect($rows->first()?->getKey())->toBe($last->getKey())
        ->and($rows->last()?->getKey())->toBe($first->getKey())
        ->and($rows->first()?->relationLoaded('invoice'))->toBeTrue();
});

it('pages by the allowed sizes and falls back for others', function (string $query, int $expected) {
    foreach (range(1, 120) as $i) {
        gadget();
    }

    expect(gadgetQuery($query)->rows()->count())->toBe($expected);
})->with([
    'default' => ['', 25],
    'fifty' => ['per_page=50', 50],
    'hundred' => ['per_page=100', 100],
    'not an option' => ['per_page=7', 25],
    'above the maximum' => ['per_page=101', 25],
    'negative' => ['per_page=-1', 25],
    'zero' => ['per_page=0', 25],
]);

it('rejects a non-integer page size and a page past the cap', function (string $query, string $key, ?string $message) {
    try {
        gadgetQuery($query)->rows();
        $this->fail('Expected a validation error.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($key);

        if ($message !== null) {
            expect($e->errors()[$key][0])->toBe($message);
        }
    }
})->with([
    ['per_page=abc', 'per_page', null],
    ['page=abc', 'page', null],
    ['page=401', 'page', 'Narrow the filters'],
]);

it('accepts the last page inside the cap', function () {
    expect(gadgetQuery('page=400')->rows()->count())->toBe(0);
});

it('caps the count with one limited query', function () {
    config(['lathe.limits.count_cap' => 3]);
    foreach (range(1, 4) as $i) {
        gadget();
    }
    $queries = [];
    DB::listen(function ($event) use (&$queries) {
        $queries[] = $event->sql;
    });

    $count = gadgetQuery()->count();

    expect($count->value)->toBe(3)
        ->and($count->capped)->toBeTrue()
        ->and($queries)->toHaveCount(1)
        ->and($queries[0])->toContain('limit 4');
});

it('does not cap a count at the cap', function () {
    config(['lathe.limits.count_cap' => 3]);
    foreach (range(1, 3) as $i) {
        gadget();
    }

    $count = gadgetQuery()->count();

    expect($count->value)->toBe(3)->and($count->capped)->toBeFalse();
});

it('reads the index view state only in the index context', function (QueryContext $context) {
    $gadget = gadget();

    expect(gadgetQuery('filter[nope]=1&tab=nope&sort=nope', $context)->find($gadget->id)->is($gadget))->toBeTrue();
})->with([QueryContext::Detail, QueryContext::Edit, QueryContext::Replicate]);
