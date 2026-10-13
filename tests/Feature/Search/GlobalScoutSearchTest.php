<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Laravel\Scout\Searchable;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Tests\Fixtures\Models\Gadget;
use Workbench\App\Models\User;

class ScoutTeamGadget extends Gadget
{
    use Searchable;

    protected $table = 'gadgets';
}

class GlobalScoutGadgets extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(ScoutTeamGadget::class)->title('name')->globallySearchable()->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }

    /**
     * Stands in for an external index that knows nothing about teams: it matches every row.
     */
    public static function scoutQuery(LatheRequest $request, mixed $query): mixed
    {
        $query->callback = fn ($eloquent, $builder, string $term) => $eloquent->withoutGlobalScopes()->where('name', 'like', "%{$term}%");

        return $query;
    }
}

test('global_search_applies_scout_scope', function () {
    config(['scout.driver' => 'collection']);
    $this->artisan('migrate')->assertSuccessful();
    Schema::create('gadgets', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->unsignedInteger('team_id');
        $table->foreignId('invoice_id')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
    Gadget::withoutGlobalScopes()->insert([
        ['name' => 'Sprocket ours', 'team_id' => 1],
        ['name' => 'Sprocket theirs', 'team_id' => 2],
    ]);
    Gate::define('viewLathe', fn () => true);
    Gate::define('globalSearch', fn () => true);
    Lathe::registerResources(GlobalScoutGadgets::class);
    $this->actingAs(User::forceCreate(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => bcrypt('secret')]));

    $groups = collect($this->getJson('/lathe/api/search?q=sprocket')->assertOk()->json('groups'))->keyBy('resourceName');

    expect(array_column($groups['global-scout-gadgets']['results'], 'title'))->toBe(['Sprocket ours']);
})->group('regression');
