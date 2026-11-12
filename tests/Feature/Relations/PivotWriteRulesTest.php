<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\Product;
use Workbench\App\Models\Tag;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

/** Products whose Tags field is on the form, with the workbench's Position rules, picking from PivotWriteTags. */
class PivotWriteProducts extends Resource
{
    public static function uriKey(): string
    {
        return 'pivot-write-products';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Product::class)->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::text('Name')->required(),
            Field::morphToMany('Tags')->resource(PivotWriteTags::class)->pivotFields([$this->position()])->showOnUpdating(),
        ];
    }

    protected function position(): Field
    {
        return Field::number('Position')->min(0)->max(1000)->step(1)->rules('integer')->required();
    }
}

class PivotReadonlyProducts extends PivotWriteProducts
{
    public static function uriKey(): string
    {
        return 'pivot-readonly-products';
    }

    protected function position(): Field
    {
        return Field::number('Position')->readonly();
    }
}

class PivotHiddenProducts extends PivotWriteProducts
{
    public static function uriKey(): string
    {
        return 'pivot-hidden-products';
    }

    protected function position(): Field
    {
        return Field::number('Position')->canSee(fn () => false);
    }
}

/** Tags whose relatable scope hides "sale". */
class PivotWriteTags extends Resource
{
    public static function uriKey(): string
    {
        return 'pivot-write-tags';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Tag::class)->canonical(false)->title('name')->allowAll();
    }

    public static function relatableQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query->where('name', '!=', 'sale');
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::text('Name')];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    Lathe::registerResources(PivotWriteProducts::class, PivotReadonlyProducts::class, PivotHiddenProducts::class, PivotWriteTags::class);
    Product::findOrFail(1)->tags()->sync([5 => ['position' => 1], 7 => ['position' => 2]]);
});

function pivotPosition(int $tagId): mixed
{
    return Product::findOrFail(1)->tags()->where('tags.id', $tagId)->first()?->pivot->position;
}

it('answers 422 on the tag whose form position breaks the pivot field rules', function () {
    $this->putJson('/lathe/api/resources/pivot-write-products/1', [
        'name' => 'Renamed',
        'tags' => [5, 7],
        'tags_pivot' => ['5' => ['position' => 5000], '7' => ['position' => 2]],
    ])->assertStatus(422)->assertJsonValidationErrors(['tags_pivot.5.position']);

    expect(pivotPosition(5))->toBe(1);
});

it('saves a form position inside the pivot field rules', function () {
    $this->putJson('/lathe/api/resources/pivot-write-products/1', [
        'name' => 'Renamed',
        'tags' => [5, 7],
        'tags_pivot' => ['5' => ['position' => 7], '7' => ['position' => 2]],
    ])->assertOk();

    expect(pivotPosition(5))->toBe(7);
});

it('keeps a readonly pivot value on the form, attach and update pivot', function () {
    $this->putJson('/lathe/api/resources/pivot-readonly-products/1', [
        'name' => 'Renamed',
        'tags' => [5, 7],
        'tags_pivot' => ['5' => ['position' => 9], '7' => ['position' => 9]],
    ])->assertOk();
    $this->putJson('/lathe/api/resources/pivot-readonly-products/1/tags/7/pivot', ['pivot' => ['position' => 9]])->assertOk();
    $this->postJson('/lathe/api/resources/pivot-readonly-products/1/tags', ['related_id' => 3, 'pivot' => ['position' => 9]])->assertCreated();

    expect([pivotPosition(5), pivotPosition(7), pivotPosition(3)])->toBe([1, 2, 0]);
});

it('keeps a pivot value the user cannot see on the form, attach and update pivot', function () {
    $this->putJson('/lathe/api/resources/pivot-hidden-products/1', [
        'name' => 'Renamed',
        'tags' => [5, 7],
        'tags_pivot' => ['5' => ['position' => 9], '7' => ['position' => 9]],
    ])->assertOk();
    $this->putJson('/lathe/api/resources/pivot-hidden-products/1/tags/7/pivot', ['pivot' => ['position' => 9]])->assertOk();
    $this->postJson('/lathe/api/resources/pivot-hidden-products/1/tags', ['related_id' => 3, 'pivot' => ['position' => 9]])->assertCreated();

    expect([pivotPosition(5), pivotPosition(7), pivotPosition(3)])->toBe([1, 2, 0]);
});

it('answers 404 on detach and update pivot for a tag outside the related relatable scope', function () {
    Product::findOrFail(1)->tags()->attach(1, ['position' => 4]);

    $this->deleteJson('/lathe/api/resources/pivot-write-products/1/tags/1')->assertNotFound();
    $this->putJson('/lathe/api/resources/pivot-write-products/1/tags/1/pivot', ['pivot' => ['position' => 8]])->assertNotFound();

    expect(pivotPosition(1))->toBe(4);
});
