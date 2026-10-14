<?php

use Illuminate\Support\Facades\Gate;
use Lathe\Field\Contracts\FilterableField;
use Lathe\Field\Field;
use Lathe\Filters\RelationFilter;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Lathe\Resources\Comment as CommentResource;
use Workbench\App\Lathe\Resources\Order as OrderResource;
use Workbench\App\Lathe\Resources\Post;
use Workbench\App\Lathe\Resources\Product;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class FilteredCustomerOrders extends OrderResource
{
    public static function uriKey(): string
    {
        return 'filtered-customer-orders';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canonical(false)->allowAll();
    }

    public function fieldsForIndex(LatheRequest $request): array
    {
        return [Field::text('Number'), Field::belongsTo('Customer')->filterable()];
    }
}

class FilteredCommentables extends CommentResource
{
    public static function uriKey(): string
    {
        return 'filtered-commentables';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canonical(false)->allowAll();
    }

    public function fieldsForIndex(LatheRequest $request): array
    {
        return [Field::textarea('Body'), Field::morphTo('Commentable')->types([Post::class, Product::class])->filterable()];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    Lathe::registerResources(FilteredCustomerOrders::class, FilteredCommentables::class);
});

it('marks belongsTo, belongsToMany and morphTo as filterable field types', function () {
    expect(Field::belongsTo('Customer'))->toBeInstanceOf(FilterableField::class)
        ->and(Field::belongsToMany('Roles'))->toBeInstanceOf(FilterableField::class)
        ->and(Field::morphTo('Commentable'))->toBeInstanceOf(FilterableField::class)
        ->and(Field::belongsTo('Customer')->filterable()->makeFieldFilter())->toBeInstanceOf(RelationFilter::class);
});

it('generates field_customer and keeps only that customer\'s orders', function () {
    $customer = Order::findOrFail(12)->customer_id;

    $rows = $this->getJson("/lathe/api/resources/filtered-customer-orders?per_page=100&filter[field_customer]={$customer}")->assertOk()->json('table.rows');

    expect($rows)->not->toBe([])
        ->and(collect($rows)->pluck('id')->sort()->values()->all())
        ->toBe(Order::where('customer_id', $customer)->orderBy('id')->limit(100)->pluck('id')->all());
});

it('serves the customers as a remote picker that types ahead, 25 a page', function () {
    $filter = collect($this->getJson('/lathe/api/resources/filtered-customer-orders/filters')->assertOk()->json('filters'))->firstWhere('key', 'field_customer');

    expect($filter['remote'])->toBeTrue()
        ->and($filter['options'] ?? null)->toBeNull();

    $json = $this->getJson('/lathe/api/resources/filtered-customer-orders/filters/field_customer/options?q=ann')->assertOk()->json();

    expect(count($json['options']))->toBeLessThanOrEqual(25)->toBeGreaterThanOrEqual(3);

    foreach ($json['options'] as $option) {
        expect(str_starts_with(strtolower($option['label']), 'ann'))->toBeTrue();
    }
});

it('filters a morphTo field by its type', function () {
    $filter = collect($this->getJson('/lathe/api/resources/filtered-commentables/filters')->assertOk()->json('filters'))->firstWhere('key', 'field_commentable');

    expect($filter['remote'])->toBeFalse();

    $rows = $this->getJson('/lathe/api/resources/filtered-commentables?per_page=100&filter[field_commentable]=posts')->assertOk()->json('table.rows');

    expect($rows)->not->toBe([])
        ->and(Comment::whereIn('id', collect($rows)->pluck('id'))->pluck('commentable_type')->unique()->values()->all())->toBe([Workbench\App\Models\Post::class]);
});
