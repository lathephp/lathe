<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Scout\Searchable;
use Lathe\Exceptions\InvalidConfigurationException;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\ResourceQuery;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Resources\ResourceRegistry;

class ScoutGizmo extends Model
{
    use Searchable;

    protected $table = 'scout_gizmos';

    protected $guarded = [];

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return ['name' => $this->name];
    }
}

class PlainGizmo extends Model
{
    protected $table = 'scout_gizmos';
}

class ScoutGizmos extends Resource
{
    public static int $scoutQueries = 0;

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(ScoutGizmo::class)->scoutSearchResults(3)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::make('Name')->sortable(), Field::make('Rank')->sortable()];
    }

    public static function scoutQuery(LatheRequest $request, mixed $query): mixed
    {
        static::$scoutQueries++;

        return $query;
    }
}

class ForcedScoutGizmos extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(PlainGizmo::class)->useScout(true)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }
}

beforeEach(function () {
    config(['scout.driver' => 'collection']);
    Schema::create('scout_gizmos', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->integer('rank');
        $table->timestamps();
    });

    foreach (range(1, 10) as $rank) {
        DB::table('scout_gizmos')->insert(['name' => "Widget {$rank}", 'rank' => $rank]);
    }
    DB::table('scout_gizmos')->insert(['name' => 'Sprocket', 'rank' => 99]);
    ScoutGizmos::$scoutQueries = 0;
});

function scoutRows(string $query): array
{
    $request = LatheRequest::createFrom(Request::create('/lathe/api/resources/scout-gizmos?'.$query));

    return (new ResourceQuery(ScoutGizmos::make(), $request))->rows()->pluck('rank')->all();
}

it('searches through Scout and keeps only the first scoutSearchResults keys', function () {
    expect(ScoutGizmos::make()->usesScout())->toBeTrue()
        ->and(scoutRows('q=widget'))->toHaveCount(3)
        ->and(ScoutGizmos::$scoutQueries)->toBe(1);
});

it('sorts and pages the Scout keys like any other rows', function () {
    $ranks = scoutRows('q=widget&sort=-rank');

    expect($ranks)->toBe(collect($ranks)->sortDesc()->values()->all())
        ->and(scoutRows('q=sprocket'))->toBe([99]);
});

it('runs no Scout search without a term', function () {
    expect(scoutRows(''))->toHaveCount(11)
        ->and(ScoutGizmos::$scoutQueries)->toBe(0);
});

it('fails the manifest for useScout(true) on a model without the Scout trait', function () {
    $registry = app(ResourceRegistry::class);
    $registry->register(ForcedScoutGizmos::class);

    expect(fn () => $registry->manifest())->toThrow(InvalidConfigurationException::class, 'ForcedScoutGizmos');
});
