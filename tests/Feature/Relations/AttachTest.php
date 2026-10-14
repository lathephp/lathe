<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(AttachDuplicateProducts::class, AttachScopedProducts::class, AttachScopedTags::class, AttachNoMethodProducts::class, AttachNoSevenProducts::class, AttachSpyProducts::class);
    AttachSpyPolicy::$calls = [];
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

/** Products whose Tags field takes the same tag more than once. */
class AttachDuplicateProducts extends Resource
{
    public static function uriKey(): string
    {
        return 'attach-duplicate-products';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Product::class)->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::morphToMany('Tags')->pivotFields([Field::number('Position')->min(0)->required()])->allowDuplicateRelations(),
        ];
    }
}

/** Products whose Tags field picks from AttachScopedTags, which hides "sale". */
class AttachScopedProducts extends AttachDuplicateProducts
{
    public static function uriKey(): string
    {
        return 'attach-scoped-products';
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::morphToMany('Tags')->resource(AttachScopedTags::class)->pivotFields([Field::number('Position')->required()]),
        ];
    }
}

class AttachScopedTags extends Resource
{
    public static function uriKey(): string
    {
        return 'attach-scoped-tags';
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

/** A policy with no attachTags method. */
class AttachNoMethodPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }
}

class AttachNoMethodProducts extends AttachDuplicateProducts
{
    public static ?string $policy = AttachNoMethodPolicy::class;

    public static function uriKey(): string
    {
        return 'attach-no-method-products';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Product::class)->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::morphToMany('Tags')->pivotFields([Field::number('Position')->required()])];
    }
}

/** A policy that refuses tag 7 by its id. */
class AttachNoSevenPolicy extends AttachNoMethodPolicy
{
    /**
     * @param  array<int, int|string>  $ids
     */
    public function attachTags(User $user, Product $product, array $ids): bool
    {
        return ! in_array(7, $ids, true);
    }
}

class AttachNoSevenProducts extends AttachNoMethodProducts
{
    public static ?string $policy = AttachNoSevenPolicy::class;

    public static function uriKey(): string
    {
        return 'attach-no-seven-products';
    }
}

/** A policy that records each ability call and its ids. */
class AttachSpyPolicy extends AttachNoMethodPolicy
{
    /** @var list<array{string, int|string, array<int, int|string>}> */
    public static array $calls = [];

    /**
     * @param  array<int, int|string>  $ids
     */
    public function attachTags(User $user, Product $product, array $ids): bool
    {
        self::$calls[] = ['attachTags', $product->getKey(), $ids];

        return true;
    }

    /**
     * @param  array<int, int|string>  $ids
     */
    public function detachTags(User $user, Product $product, array $ids): bool
    {
        self::$calls[] = ['detachTags', $product->getKey(), $ids];

        return true;
    }
}

class AttachSpyProducts extends AttachNoMethodProducts
{
    public static ?string $policy = AttachSpyPolicy::class;

    public static function uriKey(): string
    {
        return 'attach-spy-products';
    }
}

/**
 * The position cell value of each listed tag of product 1, keyed by tag name.
 *
 * @return array<string, mixed>
 */
function attachedPositions(): array
{
    $rows = test()->getJson('/lathe/api/resources/products/1/tags?perPage=100')->assertOk()->json('rows');

    $positions = [];

    foreach (is_array($rows) ? $rows : [] as $row) {
        $positions[Tag::findOrFail($row['related_id'])->name] = $row['cells']['position']['value'];
    }

    return $positions;
}

it('attaches a tag with its pivot position, updates the position and detaches it', function () {
    $this->postJson('/lathe/api/resources/products/1/tags', ['related_id' => 1, 'pivot' => ['position' => 1]])
        ->assertCreated()
        ->assertExactJson(['attached' => true]);

    expect(attachedPositions())->toHaveKey('sale', 1);

    $this->putJson('/lathe/api/resources/products/1/tags/1/pivot', ['pivot' => ['position' => 2]])
        ->assertOk()
        ->assertExactJson(['updated' => true]);

    expect(attachedPositions())->toHaveKey('sale', 2);

    $this->deleteJson('/lathe/api/resources/products/1/tags/1')
        ->assertOk()
        ->assertExactJson(['detached' => true]);

    expect(attachedPositions())->not->toHaveKey('sale');
});

it('keys a pivot validation error under pivot.<attribute>', function () {
    $this->postJson('/lathe/api/resources/products/1/tags', ['related_id' => 1, 'pivot' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['pivot.position']);

    expect(DB::table('taggables')->where('taggable_id', 1)->where('tag_id', 1)->count())->toBe(0);
});

it('refuses to attach a tag that is already attached', function () {
    $this->postJson('/lathe/api/resources/products/1/tags', ['related_id' => 1, 'pivot' => ['position' => 1]])->assertCreated();

    $this->postJson('/lathe/api/resources/products/1/tags', ['related_id' => 1, 'pivot' => ['position' => 2]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['related_id' => 'This related id is already attached.']);

    expect(DB::table('taggables')->where('taggable_id', 1)->where('tag_id', 1)->count())->toBe(1);
});

it('attaches a tag twice when duplicates are allowed, but never an identical row', function () {
    $url = '/lathe/api/resources/attach-duplicate-products/1/tags';

    $this->postJson($url, ['related_id' => 1, 'pivot' => ['position' => 1]])->assertCreated();
    $this->postJson($url, ['related_id' => 1, 'pivot' => ['position' => 2]])->assertCreated();
    $this->postJson($url, ['related_id' => 1, 'pivot' => ['position' => 2]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['related_id' => 'This related id is already attached with the same values.']);

    expect(DB::table('taggables')->where('taggable_id', 1)->where('tag_id', 1)->orderBy('position')->pluck('position')->all())->toBe([1, 2]);
});

it('needs a pivot_id to detach or update one of several rows of the same tag', function () {
    $url = '/lathe/api/resources/attach-duplicate-products/1/tags';
    $this->postJson($url, ['related_id' => 1, 'pivot' => ['position' => 1]])->assertCreated();
    $this->postJson($url, ['related_id' => 1, 'pivot' => ['position' => 2]])->assertCreated();
    $second = DB::table('taggables')->where('taggable_id', 1)->where('tag_id', 1)->where('position', 2)->value('id');

    $this->deleteJson("{$url}/1")->assertStatus(422)->assertJsonValidationErrors(['pivot_id']);
    $this->putJson("{$url}/1/pivot", ['pivot' => ['position' => 5]])->assertStatus(422)->assertJsonValidationErrors(['pivot_id']);

    $this->putJson("{$url}/1/pivot?pivot_id={$second}", ['pivot' => ['position' => 5]])->assertOk();
    expect(DB::table('taggables')->where('taggable_id', 1)->where('tag_id', 1)->orderBy('position')->pluck('position')->all())->toBe([1, 5]);

    $this->deleteJson("{$url}/1?pivot_id={$second}")->assertOk()->assertExactJson(['detached' => true]);
    expect(DB::table('taggables')->where('taggable_id', 1)->where('tag_id', 1)->pluck('position')->all())->toBe([1]);
});

it('refuses a related id outside the related Resource\'s relatable scope', function () {
    $this->postJson('/lathe/api/resources/attach-scoped-products/1/tags', ['related_id' => 1, 'pivot' => ['position' => 1]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['related_id']);

    $this->postJson('/lathe/api/resources/attach-scoped-products/1/tags', ['related_id' => 3, 'pivot' => ['position' => 1]])
        ->assertCreated();
});

it('answers 404 to detach or update a tag that is not attached', function () {
    $this->deleteJson('/lathe/api/resources/products/1/tags/1')->assertNotFound();
    $this->putJson('/lathe/api/resources/products/1/tags/1/pivot', ['pivot' => ['position' => 2]])->assertNotFound();
});

it('denies the attach to a user the policy refuses', function () {
    $this->actingAs(User::where('email', 'editor@lathephp.com')->firstOrFail());

    $this->postJson('/lathe/api/resources/products/1/tags', ['related_id' => 1, 'pivot' => ['position' => 1]])->assertForbidden();

    expect(DB::table('taggables')->where('taggable_id', 1)->where('tag_id', 1)->count())->toBe(0);
});

test('attach_denied_when_policy_lacks_method', function () {
    $this->postJson('/lathe/api/resources/attach-no-method-products/1/tags', ['related_id' => 1, 'pivot' => ['position' => 1]])->assertForbidden();

    expect(DB::table('taggables')->where('taggable_id', 1)->where('tag_id', 1)->count())->toBe(0);
})->group('regression');

it('asks the policy with the submitted ids', function () {
    $this->postJson('/lathe/api/resources/attach-no-seven-products/1/tags', ['related_id' => 7, 'pivot' => ['position' => 1]])->assertForbidden();
    $this->postJson('/lathe/api/resources/attach-no-seven-products/1/tags', ['related_id' => 1, 'pivot' => ['position' => 1]])->assertCreated();
});

it('calls attachTags for a pivot update and detachTags for a detach, with the related id', function () {
    $this->putJson('/lathe/api/resources/attach-spy-products/1/tags/4/pivot', ['pivot' => ['position' => 3]])->assertOk();
    $this->deleteJson('/lathe/api/resources/attach-spy-products/1/tags/4')->assertOk();

    expect(AttachSpyPolicy::$calls)->toBe([['attachTags', 1, [4]], ['detachTags', 1, [4]]]);
});

it('answers 404 for an unknown relationship, a relation that does not attach and a missing parent', function () {
    $this->postJson('/lathe/api/resources/products/1/nope', ['related_id' => 1])->assertNotFound();
    $this->postJson('/lathe/api/resources/orders/12/items', ['related_id' => 1])->assertNotFound();
    $this->postJson('/lathe/api/resources/products/99999999/tags', ['related_id' => 1, 'pivot' => ['position' => 1]])->assertNotFound();
});
