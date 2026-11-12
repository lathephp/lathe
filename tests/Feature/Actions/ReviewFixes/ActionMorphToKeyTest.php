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

class MorphedMerge extends Action
{
    /** @var list<mixed> */
    public static array $received = [];

    public function fields(LatheRequest $request): array
    {
        return [
            Field::morphTo('Subject', 'subject')->types([TagResource::class])
                ->relatableQuery(fn (LatheRequest $request, Builder $query) => $query->where('name', '!=', 'Hidden')),
        ];
    }

    public function handle(ActionFields $fields, Collection $models): ActionResult
    {
        static::$received[] = $fields->get('subject');

        return ActionResult::message('Merged.');
    }
}

class MorphedTags extends Resource
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
        return [MorphedMerge::make()];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    MorphedMerge::$received = [];
    Lathe::registerResources(MorphedTags::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('refuses an action morphTo key the picker would not offer', function (string $case) {
    $source = Tag::query()->orderBy('id')->value('id');
    $key = $case === 'hidden' ? Tag::query()->create(['name' => 'Hidden'])->id : 999999;

    LatheTest::resource('morphed-tags')->runAction('morphed-merge', ['mode' => 'ids', 'ids' => [$source]], ['subject' => ['type' => TagResource::uriKey(), 'id' => $key]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['subject' => 'The Subject must be a valid relation.']);

    expect(MorphedMerge::$received)->toBe([]);
})->with(['hidden', 'unknown']);

it('passes an action morphTo key the picker offers', function () {
    [$source, $target] = Tag::query()->orderBy('id')->limit(2)->pluck('id')->all();

    LatheTest::resource('morphed-tags')->runAction('morphed-merge', ['mode' => 'ids', 'ids' => [$source]], ['subject' => ['type' => TagResource::uriKey(), 'id' => $target]])
        ->assertOk()
        ->assertJsonPath('result.message', 'Merged.');

    expect(MorphedMerge::$received)->toHaveCount(1);
});
