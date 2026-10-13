<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Lathe\Exceptions\InvalidSearchColumn;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Search\SearchableExact;
use Lathe\Search\SearchableJson;
use Lathe\Search\SearchableMorphToRelation;
use Lathe\Search\SearchableRelation;
use Lathe\Search\SearchableText;
use Workbench\App\Models\Category;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\Post;
use Workbench\App\Models\Product;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

/**
 * A Resource that only exists to declare one search strategy.
 */
abstract class StrategyResource extends Resource
{
    public function fields(LatheRequest $request): array
    {
        return [];
    }
}

class StrategyExactProduct extends StrategyResource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Product::class)->canonical(false)->searchable([new SearchableExact('sku')])->allowAll();
    }
}

class StrategyTextPost extends StrategyResource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Post::class)->canonical(false)->searchable([new SearchableText('body')])->allowAll();
    }
}

class StrategyGizmo extends Model
{
    protected $table = 'gizmos';

    protected $guarded = [];

    protected $casts = ['meta' => 'array'];
}

class StrategyJsonGizmo extends StrategyResource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(StrategyGizmo::class)->searchable([new SearchableJson('meta->color')])->allowAll();
    }
}

class StrategyJoinedOrder extends StrategyResource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->canonical(false)->searchable([new SearchableRelation('customer', 'name')])->allowAll();
    }

    public static function indexQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query->select('orders.*')->join('order_notes', 'order_notes.order_id', '=', 'orders.id');
    }
}

class StrategyPostComment extends StrategyResource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Comment::class)->searchable([new SearchableMorphToRelation('commentable', 'title', types: [Post::class])])->allowAll();
    }
}

class StrategyAnyComment extends StrategyResource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Comment::class)->canonical(false)->searchable([new SearchableMorphToRelation('commentable', 'title')])->allowAll();
    }
}

class StrategyNotAColumn
{
    public function __construct(public string $column)
    {
        //
    }
}

class StrategyBadClass extends StrategyResource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Customer::class)->canonical(false)->searchable([new StrategyNotAColumn('name')])->allowAll();
    }
}

class StrategyMissingRelation extends StrategyResource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->canonical(false)->searchable([new SearchableRelation('buyer', 'name')])->allowAll();
    }
}

class StrategyMissingColumn extends StrategyResource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Customer::class)->canonical(false)->searchable(['nickname'])->allowAll();
    }
}

class StrategyMissingRelatedColumn extends StrategyResource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->canonical(false)->searchable([new SearchableRelation('customer', 'nickname')])->allowAll();
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    Lathe::resources()->clearCache();
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::forceCreate(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret']));
});

/**
 * The sorted row ids one index request returns, 100 to a page.
 *
 * @return list<int|string>
 */
function strategyIds(string $resource, string $term): array
{
    $ids = array_column(test()->getJson("/lathe/api/resources/{$resource}?per_page=100&q=".urlencode($term))->assertOk()->json('table.rows'), 'id');
    sort($ids);

    return $ids;
}

it('matches only the whole value with SearchableExact', function () {
    $category = Category::forceCreate(['name' => 'Tools']);
    $short = Product::forceCreate(['category_id' => $category->id, 'name' => 'Short', 'sku' => 'ab-100', 'price_cents' => 1]);
    Product::forceCreate(['category_id' => $category->id, 'name' => 'Long', 'sku' => 'ab-1000', 'price_cents' => 1]);
    Lathe::registerResources(StrategyExactProduct::class);

    expect(strategyIds('strategy-exact-products', 'ab-100'))->toBe([$short->id])
        ->and(strategyIds('strategy-exact-products', 'ab-10'))->toBe([]);
});

it('finds a word in the body with SearchableText and falls back to contains on SQLite', function () {
    $quokka = Post::forceCreate(['title' => 'One', 'status' => 'draft', 'body' => 'A quiet quokka sleeps under the tree']);
    Post::forceCreate(['title' => 'Two', 'status' => 'draft', 'body' => 'No marsupial lives here']);
    Lathe::registerResources(StrategyTextPost::class);
    $sqlite = DB::connection()->getDriverName() === 'sqlite';

    expect(strategyIds('strategy-text-posts', 'quokka'))->toBe([$quokka->id])
        ->and(strategyIds('strategy-text-posts', 'uokk'))->toBe($sqlite ? [$quokka->id] : []);
});

it('reads boolean operators in a text search as plain words, never as SQL errors', function (string $term) {
    $quokka = Post::forceCreate(['title' => 'One', 'status' => 'draft', 'body' => 'A quiet quokka sleeps under the tree']);
    Lathe::registerResources(StrategyTextPost::class);

    expect(strategyIds('strategy-text-posts', $term))->toBeArray();

    // SQLite matches the whole phrase with contains, so only full text finds the word beside the operator.
    if (DB::connection()->getDriverName() !== 'sqlite') {
        expect(strategyIds('strategy-text-posts', "{$term} quokka"))->toContain($quokka->id);
    }
})->with(['"', '(', ')', '-', '+', '*', '~', '<', '>', '@', '"quokka']);

it('matches a JSON path by prefix with SearchableJson', function () {
    Schema::create('gizmos', function (Blueprint $table) {
        $table->id();
        $table->json('meta');
        $table->timestamps();
    });
    $blue = StrategyGizmo::create(['meta' => ['color' => 'blue']]);
    $black = StrategyGizmo::create(['meta' => ['color' => 'black']]);
    StrategyGizmo::create(['meta' => ['color' => 'navy-blue']]);
    StrategyGizmo::create(['meta' => ['shade' => 'blue']]);
    Lathe::registerResources(StrategyJsonGizmo::class);

    expect(strategyIds('strategy-json-gizmos', 'bl'))->toBe([$blue->id, $black->id])
        ->and(strategyIds('strategy-json-gizmos', 'blue'))->toBe([$blue->id]);
});

it('finds Orders by the name prefix of their customer', function () {
    $this->seed(DemoSeeder::class);
    $joanne = Customer::forceCreate(['name' => 'Joanne Banner', 'email' => 'jb@example.com']);
    $hidden = Order::forceCreate(['customer_id' => $joanne->id, 'number' => 'ORD-JOANNE', 'status' => 'paid', 'total_cents' => 100, 'placed_at' => now()]);

    $ids = strategyIds('orders', 'ann');
    $names = Order::with('customer')->whereKey($ids)->get()->pluck('customer.name')->unique()->all();

    expect($ids)->not->toBeEmpty()->not->toContain($hidden->id)
        ->and($names)->toContain('Ann Taylor', 'Anna Kowalski', 'Annette Fox')
        ->and(array_filter($names, fn (string $name) => ! str_starts_with(strtolower($name), 'ann')))->toBe([]);
});

it('searches the relation when indexQuery joins a table that shares name', function () {
    $ann = Customer::forceCreate(['name' => 'Ann Lee', 'email' => 'ann@example.com']);
    $bob = Customer::forceCreate(['name' => 'Bob Ray', 'email' => 'bob@example.com']);
    $first = Order::forceCreate(['customer_id' => $ann->id, 'number' => 'ORD-1', 'status' => 'paid', 'total_cents' => 100, 'placed_at' => now()]);
    $second = Order::forceCreate(['customer_id' => $bob->id, 'number' => 'ORD-2', 'status' => 'paid', 'total_cents' => 100, 'placed_at' => now()]);
    Schema::create('order_notes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('order_id');
        $table->string('name');
    });
    DB::table('order_notes')->insert([
        ['order_id' => $first->id, 'name' => 'Rush'],
        ['order_id' => $second->id, 'name' => 'Annual'],
    ]);
    Lathe::registerResources(StrategyJoinedOrder::class);

    expect(strategyIds('strategy-joined-orders', 'ann'))->toBe([$first->id]);
});

it('searches only the morph types given and every type for types []', function () {
    Schema::table('products', function (Blueprint $table) {
        $table->string('title')->nullable();
    });
    $category = Category::forceCreate(['name' => 'Tools']);
    $post = Post::forceCreate(['title' => 'Zanzibar notes', 'status' => 'draft', 'body' => 'x']);
    $product = Product::forceCreate(['category_id' => $category->id, 'name' => 'Kit', 'sku' => 'kit-1', 'price_cents' => 1, 'title' => 'Zanzibar kit']);
    $other = Post::forceCreate(['title' => 'Elsewhere', 'status' => 'draft', 'body' => 'x']);
    $onPost = Comment::forceCreate(['commentable_type' => Post::class, 'commentable_id' => $post->id, 'body' => 'a']);
    $onProduct = Comment::forceCreate(['commentable_type' => Product::class, 'commentable_id' => $product->id, 'body' => 'b']);
    Comment::forceCreate(['commentable_type' => Post::class, 'commentable_id' => $other->id, 'body' => 'c']);
    Lathe::registerResources(StrategyPostComment::class, StrategyAnyComment::class);

    expect(strategyIds('strategy-post-comments', 'zanz'))->toBe([$onPost->id])
        ->and(strategyIds('strategy-any-comments', 'zanz'))->toBe([$onPost->id, $onProduct->id]);
});

it('throws InvalidSearchColumn when the manifest is built', function (string $resource, string $message) {
    Lathe::registerResources($resource);

    expect(fn () => Lathe::resources()->manifest())->toThrow(InvalidSearchColumn::class, $message);
})->with([
    'not a SearchableColumn' => [StrategyBadClass::class, 'StrategyBadClass: searchable: StrategyNotAColumn (column "name") does not implement Lathe\Search\SearchableColumn. Implement that interface or use a strategy from Lathe\Search.'],
    'missing relation' => [StrategyMissingRelation::class, 'StrategyMissingRelation: searchable: column "name" uses the relation "buyer", which Workbench\App\Models\Order does not define. Add a buyer() relation to the model or remove the column from searchable().'],
]);

it('throws InvalidSearchColumn when lathe:cache finds a column missing from its table', function (string $resource, string $message) {
    Lathe::registerResources($resource);

    expect(fn () => Lathe::resources()->cache())->toThrow(InvalidSearchColumn::class, $message)
        ->and(file_exists(Lathe::resources()->cachePath()))->toBeFalse();
})->with([
    'missing column' => [StrategyMissingColumn::class, 'StrategyMissingColumn: searchable: column "nickname" is not in the customers table. Add the column with a migration or remove it from searchable().'],
    'missing related column' => [StrategyMissingRelatedColumn::class, 'StrategyMissingRelatedColumn: searchable: column "nickname" is not in the customers table. Add the column with a migration or remove it from searchable().'],
]);
