<?php

namespace Lathe\Tests\Feature\Actions\ReviewFixes;

use Illuminate\Database\Eloquent\Builder;
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
use Workbench\App\Lathe\Resources\Tag as TagResource;
use Workbench\App\Models\Tag;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class KeyedMerge extends Action
{
    /** @var list<mixed> */
    public static array $received = [];

    public function fields(LatheRequest $request): array
    {
        return [
            Field::belongsTo('Target', 'target', TagResource::class)
                ->relatableQuery(fn (LatheRequest $request, Builder $query) => $query->where('name', '!=', 'Hidden')),
        ];
    }

    public function handle(ActionFields $fields, Collection $models): ActionResult
    {
        static::$received[] = $fields->get('target');

        return ActionResult::message('Merged.');
    }
}

class KeyedTags extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Tag::class)->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }

    public function actions(LatheRequest $request): array
    {
        return [KeyedMerge::make()];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    KeyedMerge::$received = [];
    Lathe::registerResources(KeyedTags::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('refuses an action relation key that names no record', function () {
    $source = Tag::query()->orderBy('id')->value('id');

    LatheTest::resource('keyed-tags')->runAction('keyed-merge', ['mode' => 'ids', 'ids' => [$source]], ['target' => 999999])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['target' => 'The Target must be a valid relation.']);

    expect(KeyedMerge::$received)->toBe([]);
});

it('refuses an action relation key the picker scope hides', function () {
    $source = Tag::query()->orderBy('id')->value('id');
    $hidden = Tag::query()->create(['name' => 'Hidden']);

    LatheTest::resource('keyed-tags')->runAction('keyed-merge', ['mode' => 'ids', 'ids' => [$source]], ['target' => ['value' => $hidden->id, 'display' => 'Hidden']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['target' => 'The Target must be a valid relation.']);

    expect(KeyedMerge::$received)->toBe([]);
});

it('passes an action relation key the picker offers', function () {
    [$source, $target] = Tag::query()->orderBy('id')->limit(2)->pluck('id')->all();

    LatheTest::resource('keyed-tags')->runAction('keyed-merge', ['mode' => 'ids', 'ids' => [$source]], ['target' => $target])
        ->assertOk()
        ->assertJsonPath('result.message', 'Merged.');

    expect(KeyedMerge::$received)->toBe([$target]);
});
