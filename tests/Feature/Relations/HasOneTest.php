<?php

namespace Lathe\Tests\Feature\Relations;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Lathe\Events\ResourceCreated;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Testing\LatheTest;
use Lathe\Tests\Fixtures\Models\Image;
use Lathe\Tests\Fixtures\Models\ImagePost;
use Lathe\Tests\Fixtures\Models\Warranty;
use Lathe\Tests\Fixtures\Models\WarrantyProduct;
use RuntimeException;
use Workbench\App\Models\Category;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

/*
 * The wire of a hasOne or morphOne field on a form: the schema carries the related
 * Resource's create fields (update fields once the row exists) under `fields`, with their
 * own attributes; the value is the related row's values keyed by those attributes, or null.
 * The client posts the value under the field's attribute, so a sub-field validates and
 * fails under `warranty.months`. On detail the field carries `record` ({id, title, href})
 * or null, the `via` a Create link opens the child form under, and `canAdd`.
 */

class HasOneProducts extends Resource
{
    /** @var (Closure(LatheRequest, Model): bool)|null */
    public static ?Closure $filledWhen = null;

    public static function uriKey(): string
    {
        return 'has-one-products';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(WarrantyProduct::class)->title('name')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        $warranty = Field::hasOne('Warranty')->resource(HasOneWarranties::class);

        if (static::$filledWhen !== null) {
            $warranty->alreadyFilledWhen(static::$filledWhen);
        }

        return [
            Field::text('Name'),
            Field::text('Sku'),
            Field::number('Price Cents'),
            Field::number('Category Id'),
            $warranty,
            Field::hasOneOfMany('Latest Warranty', 'latestWarranty', HasOneWarranties::class),
        ];
    }
}

class HasOneWarranties extends Resource
{
    public static function uriKey(): string
    {
        return 'has-one-warranties';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Warranty::class)->title('type')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::belongsTo('Product')->resource(HasOneProducts::class),
            Field::text('Type')->rules('required'),
            Field::number('Months')->rules('required', 'integer', 'min:1'),
        ];
    }
}

class HasOnePosts extends Resource
{
    public static function uriKey(): string
    {
        return 'has-one-posts';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(ImagePost::class)->title('title')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::text('Title'),
            Field::text('Status'),
            Field::text('Body'),
            Field::morphOne('Image')->resource(HasOneImages::class),
        ];
    }
}

class HasOneImages extends Resource
{
    public static function uriKey(): string
    {
        return 'has-one-images';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Image::class)->title('url')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::morphTo('Imageable')->types([HasOnePosts::class])->inverse('image'),
            Field::text('Url')->rules('required'),
        ];
    }
}

function hoProduct(string $sku = 'HO-1'): WarrantyProduct
{
    $product = new WarrantyProduct;
    $product->forceFill(['name' => 'Kettle', 'sku' => $sku, 'price_cents' => 900, 'category_id' => Category::firstOrFail()->id])->save();

    return $product;
}

function hoPost(): ImagePost
{
    $post = new ImagePost;
    $post->forceFill(['title' => 'Cover story', 'status' => 'draft', 'body' => 'Text'])->save();

    return $post;
}

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function hoField(array $payload, string $attribute): array
{
    /** @var array<string, mixed> */
    return collect($payload['fields'])->firstWhere('attribute', $attribute);
}

/**
 * @return array<string, mixed>
 */
function hoDetail(string $uriKey, int $id): array
{
    /** @var array<string, mixed> */
    return test()->get("/lathe/resources/{$uriKey}/{$id}")->assertOk()->viewData('page')['props']['record'];
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Schema::dropIfExists('warranties');
    Schema::dropIfExists('images');
    Schema::create('warranties', function (Blueprint $table) {
        $table->id();
        $table->foreignId('product_id');
        $table->string('type');
        $table->integer('months');
    });
    Schema::create('images', function (Blueprint $table) {
        $table->id();
        $table->morphs('imageable');
        $table->string('url');
    });
    HasOneProducts::$filledWhen = null;
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(HasOneProducts::class, HasOneWarranties::class, HasOnePosts::class, HasOneImages::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('puts the related create fields inline in the create form, without the field pointing back', function () {
    $warranty = hoField(LatheTest::resource('has-one-products')->creationFields()->assertOk()->json(), 'warranty');

    expect($warranty['component'])->toBe('has-one-field')
        ->and($warranty['value'])->toBeNull()
        ->and(collect($warranty['fields'])->pluck('attribute')->all())->toBe(['type', 'months']);
});

it('creates the product and its warranty in one save', function () {
    $response = LatheTest::resource('has-one-products')->store([
        'name' => 'Kettle', 'sku' => 'HO-NEW', 'price_cents' => 900, 'category_id' => Category::firstOrFail()->id,
        'warranty' => ['type' => 'gold', 'months' => 12],
    ])->assertCreated();

    $warranties = Warranty::all();

    expect($warranties)->toHaveCount(1)
        ->and($warranties[0]->product_id)->toBe($response->json('id'))
        ->and($warranties[0]->type)->toBe('gold')
        ->and($warranties[0]->months)->toBe(12);
});

it('rolls the product and its warranty back together when the create fails', function () {
    $seen = null;
    Event::listen(ResourceCreated::class, function () use (&$seen) {
        $seen = Warranty::count();

        throw new RuntimeException('listener failed');
    });

    LatheTest::resource('has-one-products')->store([
        'name' => 'Kettle', 'sku' => 'HO-ROLL', 'price_cents' => 900, 'category_id' => Category::firstOrFail()->id,
        'warranty' => ['type' => 'gold', 'months' => 12],
    ])->assertServerError();

    expect($seen)->toBe(1)
        ->and(Warranty::count())->toBe(0)
        ->and(WarrantyProduct::where('sku', 'HO-ROLL')->exists())->toBeFalse();
});

it('fails a related field under its nested key', function () {
    LatheTest::resource('has-one-products')->store([
        'name' => 'Kettle', 'sku' => 'HO-BAD', 'price_cents' => 900, 'category_id' => Category::firstOrFail()->id,
        'warranty' => ['type' => 'gold', 'months' => 'soon'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['warranty.months'])->assertJsonMissingValidationErrors(['warranty.type']);

    expect(WarrantyProduct::where('sku', 'HO-BAD')->exists())->toBeFalse();
});

it('creates no warranty when none is sent, or an empty one', function () {
    $category = Category::firstOrFail()->id;

    LatheTest::resource('has-one-products')->store(['name' => 'W', 'sku' => 'HO-W', 'price_cents' => 1, 'category_id' => $category, 'warranty' => ['type' => 'gold', 'months' => 1]])->assertCreated();
    LatheTest::resource('has-one-products')->store(['name' => 'A', 'sku' => 'HO-A', 'price_cents' => 1, 'category_id' => $category])->assertCreated();
    LatheTest::resource('has-one-products')->store(['name' => 'B', 'sku' => 'HO-B', 'price_cents' => 1, 'category_id' => $category, 'warranty' => null])->assertCreated();
    LatheTest::resource('has-one-products')->store(['name' => 'C', 'sku' => 'HO-C', 'price_cents' => 1, 'category_id' => $category, 'warranty' => ['type' => '', 'months' => null]])->assertCreated();

    expect(Warranty::count())->toBe(1);
});

it('carries the related update fields and their values in the edit form', function () {
    $product = hoProduct();
    $product->warranty()->create(['type' => 'silver', 'months' => 6]);

    $warranty = hoField(LatheTest::resource('has-one-products')->updateFields($product->id)->assertOk()->json(), 'warranty');

    expect(collect($warranty['fields'])->pluck('attribute')->all())->toBe(['type', 'months'])
        ->and($warranty['value'])->toBe(['type' => 'silver', 'months' => 6]);
});

it('updates the existing warranty on edit instead of adding one', function () {
    $product = hoProduct();
    $existing = $product->warranty()->create(['type' => 'silver', 'months' => 6]);

    LatheTest::resource('has-one-products')->update($product->id, ['warranty' => ['type' => 'gold', 'months' => 24]])->assertOk();

    $warranties = Warranty::all();

    expect($warranties)->toHaveCount(1)
        ->and($warranties[0]->id)->toBe($existing->id)
        ->and($warranties[0]->type)->toBe('gold')
        ->and($warranties[0]->months)->toBe(24);
});

it('creates the warranty on edit when the product has none', function () {
    $product = hoProduct();

    LatheTest::resource('has-one-products')->update($product->id, ['warranty' => ['type' => 'gold', 'months' => 3]])->assertOk();

    expect($product->warranty()->firstOrFail()->months)->toBe(3);
});

it('shows an ofMany field on detail only, read-only, as the latest title, with no block', function () {
    $product = hoProduct();
    $product->warranty()->create(['type' => 'first', 'months' => 6]);
    $product->warranty()->create(['type' => 'latest', 'months' => 12]);

    $record = hoDetail('has-one-products', $product->id);
    $latest = hoField($record, 'latestWarranty');
    $form = LatheTest::resource('has-one-products')->creationFields()->assertOk()->json();
    $blocks = collect($record['layout'])->pluck('type')->all();

    expect($latest['readonly'])->toBeTrue()
        ->and($latest['display'])->toBe('latest')
        ->and(collect($form['fields'])->pluck('attribute')->all())->not->toContain('latestWarranty')
        ->and($blocks)->toContain('has-one-warranty')
        ->and($blocks)->not->toContain('has-one-latestWarranty');
});

it('tells the detail page the related record and whether Create shows', function () {
    $empty = hoProduct('HO-E');
    $filled = hoProduct('HO-F');
    $warranty = $filled->warranty()->create(['type' => 'gold', 'months' => 12]);

    $none = hoField(hoDetail('has-one-products', $empty->id), 'warranty');
    $one = hoField(hoDetail('has-one-products', $filled->id), 'warranty');

    expect($none['record'])->toBeNull()
        ->and($none['canAdd'])->toBeTrue()
        ->and($none['via'])->toBe("has-one-products/{$empty->id}/warranty")
        ->and($one['record'])->toBe(['id' => $warranty->id, 'title' => 'gold', 'href' => "/lathe/resources/has-one-warranties/{$warranty->id}"])
        ->and($one['canAdd'])->toBeFalse();
});

it('refuses a second warranty created under the via', function () {
    $product = hoProduct();
    $product->warranty()->create(['type' => 'gold', 'months' => 12]);

    $this->postJson("/lathe/api/resources/has-one-warranties?via=has-one-products/{$product->id}/warranty", ['type' => 'silver', 'months' => 6])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['already_filled']);

    expect(Warranty::count())->toBe(1);
});

it('lets a create under the via through when alreadyFilledWhen answers false', function () {
    HasOneProducts::$filledWhen = fn (LatheRequest $request, WarrantyProduct $parent): bool => false;
    $product = hoProduct();
    $product->warranty()->create(['type' => 'gold', 'months' => 12]);

    $this->postJson("/lathe/api/resources/has-one-warranties?via=has-one-products/{$product->id}/warranty", ['type' => 'silver', 'months' => 6])
        ->assertCreated();

    expect(Warranty::where('product_id', $product->id)->count())->toBe(2)
        ->and(hoField(hoDetail('has-one-products', $product->id), 'warranty')['canAdd'])->toBeTrue();
});

it('creates and updates a morphOne image through the post form', function () {
    $form = hoField(LatheTest::resource('has-one-posts')->creationFields()->assertOk()->json(), 'image');

    $id = LatheTest::resource('has-one-posts')->store([
        'title' => 'Cover', 'status' => 'draft', 'body' => 'Text', 'image' => ['url' => 'a.png'],
    ])->assertCreated()->json('id');

    $image = Image::firstOrFail();

    LatheTest::resource('has-one-posts')->update($id, ['image' => ['url' => 'b.png']])->assertOk();

    expect($form['component'])->toBe('morph-one-field')
        ->and(collect($form['fields'])->pluck('attribute')->all())->toBe(['url'])
        ->and([$image->imageable_type, $image->imageable_id])->toBe([ImagePost::class, $id])
        ->and(Image::count())->toBe(1)
        ->and($image->fresh()?->url)->toBe('b.png');
});

it('creates a morphOne image under the via once', function () {
    $post = hoPost();
    $url = "/lathe/api/resources/has-one-images?via=has-one-posts/{$post->id}/image";
    $imageable = ['type' => 'has-one-posts', 'id' => $post->id];

    $this->postJson($url, ['imageable' => $imageable, 'url' => 'a.png'])->assertCreated();
    $this->postJson($url, ['imageable' => $imageable, 'url' => 'b.png'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['already_filled']);

    expect($post->image()->firstOrFail()->url)->toBe('a.png')
        ->and(Image::count())->toBe(1);
});

class GuardedHasOneProducts extends HasOneProducts
{
    public static function uriKey(): string
    {
        return 'guarded-has-one-products';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->allowAll(false);
    }
}

class NoAddWarrantyPolicy
{
    public function viewAny(mixed $user): bool
    {
        return true;
    }

    public function view(mixed $user, Model $product): bool
    {
        return true;
    }

    public function create(mixed $user): bool
    {
        return true;
    }
}

it('refuses to write the warranty from the product form without addWarranty', function () {
    Lathe::registerResources(GuardedHasOneProducts::class);
    Gate::policy(WarrantyProduct::class, NoAddWarrantyPolicy::class);

    LatheTest::resource('guarded-has-one-products')->store([
        'name' => 'Kettle', 'sku' => 'HO-DENY', 'price_cents' => 900, 'category_id' => Category::firstOrFail()->id,
        'warranty' => ['type' => 'gold', 'months' => 12],
    ])->assertUnprocessable()->assertJsonValidationErrors('warranty');

    expect(Warranty::count())->toBe(0);
});
