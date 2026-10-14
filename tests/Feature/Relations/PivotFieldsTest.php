<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Lathe\Resources\Product as ProductResource;
use Workbench\App\Models\Product;
use Workbench\App\Models\Tag;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class PickyTagPolicy
{
    public static int $calls = 0;

    /** @var list<array<int, int|string>> */
    public static array $seen = [];

    public function view(User $user, Product $product): bool
    {
        return true;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return list<int|string>|bool
     */
    public function attachTags(User $user, Product $product, array $ids): array|bool
    {
        self::$calls++;
        self::$seen[] = $ids;

        // Answers with the ids it allows: every one but the "sale" tag.
        return array_values(array_filter($ids, fn (int|string $id) => (int) $id !== (int) Tag::where('name', 'sale')->value('id')));
    }
}

class ClosurePivotProducts extends ProductResource
{
    public static ?Closure $seen = null;

    public static function uriKey(): string
    {
        return 'closure-pivot-products';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::text('Name'),
            Field::morphToMany('Tags')->pivotFields(function (LatheRequest $request, ?Model $related) {
                return [Field::number('Position')->required()->help($related === null ? 'new' : 'for '.$related->getAttribute('name'))];
            }),
        ];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('answers the pivot form of the attach modal and of one attached row', function () {
    $product = Product::findOrFail(2);
    $tag = $product->tags()->orderBy('tags.id')->firstOrFail();

    $attach = $this->getJson('/lathe/api/resources/products/2/tags/pivot-fields')->assertOk()->json('fields');
    expect(array_column($attach, 'key'))->toBe(['position']);

    $row = $this->getJson("/lathe/api/resources/products/2/tags/{$tag->id}/pivot-fields")->assertOk()->json('fields');
    expect($row[0]['value'])->toBe($tag->pivot->position);

    $this->getJson('/lathe/api/resources/products/2/tags/99999999/pivot-fields')->assertNotFound();
});

it('hands a pivotFields Closure the request and the related record', function () {
    Lathe::registerResources(ClosurePivotProducts::class);
    $tag = Product::findOrFail(2)->tags()->orderBy('tags.id')->firstOrFail();

    expect($this->getJson('/lathe/api/resources/closure-pivot-products/2/tags/pivot-fields')->assertOk()->json('fields.0.help'))->toBe('new')
        ->and($this->getJson("/lathe/api/resources/closure-pivot-products/2/tags/{$tag->id}/pivot-fields")->assertOk()->json('fields.0.help'))->toBe("for {$tag->name}");
});

it('drops the tags the policy denies from the attach picker with one policy call', function () {
    Gate::policy(Product::class, PickyTagPolicy::class);
    PickyTagPolicy::$calls = 0;

    $values = collect($this->getJson('/lathe/api/resources/products/1/tags/associatable')->assertOk()->json('items'))->pluck('value')->all();

    expect(PickyTagPolicy::$calls)->toBe(1)
        ->and(PickyTagPolicy::$seen[0])->toHaveCount(25)
        ->and($values)->toHaveCount(24)
        ->and($values)->not->toContain(Tag::where('name', 'sale')->value('id'));
});
