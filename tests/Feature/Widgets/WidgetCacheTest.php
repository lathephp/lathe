<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lathe\Actions\Import\Import;
use Lathe\Actions\Import\ProcessImport;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Support\JobContext;
use Lathe\Tests\Fixtures\Dashboards\Cards;
use Lathe\Tests\Fixtures\Widgets\AttributedCard;
use Lathe\Tests\Fixtures\Widgets\CancelledOrders;
use Lathe\Tests\Fixtures\Widgets\FluentCard;
use Lathe\Tests\Fixtures\Widgets\OrderCount;
use Lathe\Tests\Fixtures\Widgets\TaggedCount;
use Lathe\Tests\Fixtures\Widgets\TrendProbe;
use Lathe\Tests\Fixtures\Widgets\TtlOrders;
use Lathe\Tests\Fixtures\Widgets\ValueCustomers;
use Lathe\Tests\Fixtures\Widgets\ValueOrders;
use Lathe\Tests\Fixtures\Widgets\ValuePosts;
use Lathe\Tests\Fixtures\Widgets\ValueProducts;
use Lathe\Widgets\Widget;
use Lathe\Widgets\WidgetCache;
use Workbench\App\Lathe\Actions\ImportProducts;
use Workbench\App\Models\Product;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

/**
 * Sums the tag pivot positions, so a pivot update changes its value.
 */
class TagPositionSum extends Widget
{
    public function calculate(LatheRequest $request): mixed
    {
        $this->cacheTags(['taggables']);

        return (int) DB::table('taggables')->sum('position');
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    TtlOrders::$ttl = null;
    Cards::$widgets = [new ValueOrders, new TtlOrders, new CancelledOrders, new ValuePosts, new ValueProducts, new TaggedCount];
    Lathe::registerDashboards(Cards::class);
});

function cacheUser(string $email = 'admin@lathephp.com'): User
{
    return User::where('email', $email)->firstOrFail();
}

/**
 * The SQL the closure runs.
 *
 * @return list<string>
 */
function queriesOf(Closure $run): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $run();
    DB::disableQueryLog();

    return array_values(array_map(fn (array $query) => (string) $query['query'], DB::getQueryLog()));
}

/**
 * The card's value on the Cards dashboard.
 */
function cardValue(string $widget, string $range = '30'): mixed
{
    $json = test()->getJson("/lathe/api/dashboards/cards/widgets/{$widget}?range={$range}")->assertOk()->json('value');

    return is_array($json) ? $json['value'] : $json;
}

/**
 * The newest order that is not cancelled, which every 30-day count includes.
 *
 * @return array{id: int, number: string, customer_id: int}
 */
function liveOrder(): array
{
    $order = DB::table('orders')->where('status', '!=', 'cancelled')->orderByDesc('id')->first(['id', 'number', 'customer_id']);

    return ['id' => (int) $order->id, 'number' => (string) $order->number, 'customer_id' => (int) $order->customer_id];
}

it('serves a value from the cache for 60 seconds, then runs its queries again', function () {
    $this->actingAs(cacheUser());
    $url = '/lathe/api/dashboards/cards/widgets/value-orders?range=30';

    expect(queriesOf(fn () => $this->getJson($url)->assertOk()))->toHaveCount(2);

    $this->travel(59)->seconds();
    expect(queriesOf(fn () => $this->getJson($url)->assertOk()))->toHaveCount(0);

    $this->travel(2)->seconds();
    expect(queriesOf(fn () => $this->getJson($url)->assertOk()))->toHaveCount(2);
});

it('runs the queries on every request when cacheFor() returns 0', function () {
    TtlOrders::$ttl = 0;
    $this->actingAs(cacheUser());

    expect(queriesOf(fn () => cardValue('ttl-orders')))->toHaveCount(2)
        ->and(queriesOf(fn () => cardValue('ttl-orders')))->toHaveCount(2);
});

it('keeps the value for the seconds cacheFor() returns', function () {
    TtlOrders::$ttl = 120;
    $this->actingAs(cacheUser());
    cardValue('ttl-orders');

    $this->travel(119)->seconds();
    expect(queriesOf(fn () => cardValue('ttl-orders')))->toHaveCount(0);

    $this->travel(2)->seconds();
    expect(queriesOf(fn () => cardValue('ttl-orders')))->toHaveCount(2);
});

it('takes the default time to live from limits.metrics_ttl', function () {
    config(['lathe.limits.metrics_ttl' => 30]);
    $this->actingAs(cacheUser());
    cardValue('value-orders');

    $this->travel(29)->seconds();
    expect(queriesOf(fn () => cardValue('value-orders')))->toHaveCount(0);

    $this->travel(2)->seconds();
    expect(queriesOf(fn () => cardValue('value-orders')))->toHaveCount(2);
});

it('shows a created order on the next request', function () {
    $this->actingAs(cacheUser());
    $before = cardValue('value-orders');

    $this->postJson('/lathe/api/resources/orders', [
        'number' => 'CACHE-0001',
        'status' => 'pending',
        'placed_at' => now()->format('Y-m-d H:i:s'),
        'total_cents' => 12500,
        'customer' => ['value' => 1],
    ])->assertCreated();

    expect($before)->toBeInt()->and(cardValue('value-orders'))->toBe($before + 1);
});

it('shows an updated order on the next request', function () {
    $this->actingAs(cacheUser());
    $order = liveOrder();
    $before = cardValue('cancelled-orders');

    $this->putJson("/lathe/api/resources/orders/{$order['id']}", [
        'number' => $order['number'],
        'status' => 'cancelled',
        'placed_at' => now()->format('Y-m-d H:i:s'),
        'total_cents' => 12500,
        'customer' => ['value' => $order['customer_id']],
    ])->assertOk();

    expect($before)->toBeInt()->and(cardValue('cancelled-orders'))->toBe($before + 1);
});

it('shows a deleted order on the next request', function () {
    $this->actingAs(cacheUser());
    $before = cardValue('value-orders');

    $this->deleteJson('/lathe/api/resources/orders/'.liveOrder()['id'])->assertOk();

    expect($before)->toBeInt()->and(cardValue('value-orders'))->toBe($before - 1);
});

it('shows a restored post on the next request', function () {
    $this->actingAs(cacheUser());
    $before = cardValue('value-posts', 'ALL');

    $this->putJson('/lathe/api/resources/posts/195/restore')->assertOk();

    expect($before)->toBeInt()->and(cardValue('value-posts', 'ALL'))->toBe($before + 1);
});

it('flushes a card tagged with the pivot table when a tag is attached or detached', function () {
    $this->actingAs(cacheUser());
    $before = cardValue('tagged-count');
    $free = DB::table('tags')->whereNotIn('id', DB::table('taggables')->where('taggable_type', Product::class)->where('taggable_id', 1)->select('tag_id'))->value('id');

    $this->postJson('/lathe/api/resources/products/1/tags', ['related_id' => $free, 'pivot' => ['position' => 1]])->assertCreated();
    expect($before)->toBeInt()->and(cardValue('tagged-count'))->toBe($before + 1);

    $this->deleteJson("/lathe/api/resources/products/1/tags/{$free}")->assertOk();
    expect(cardValue('tagged-count'))->toBe($before);
});

it('shows an inline edit on the next request', function () {
    $this->actingAs(cacheUser());
    $before = cardValue('cancelled-orders');

    $this->patchJson('/lathe/api/resources/orders/'.liveOrder()['id'].'/field/status', ['value' => 'cancelled'])->assertOk();

    expect($before)->toBeInt()->and(cardValue('cancelled-orders'))->toBe($before + 1);
});

it('flushes a card tagged with the pivot table when a pivot row is updated', function () {
    Cards::$widgets = [new TagPositionSum];
    $this->actingAs(cacheUser());
    $before = cardValue('tag-position-sum');
    $row = DB::table('taggables')->where('taggable_type', Product::class)->where('taggable_id', 2)->orderBy('tag_id')->first(['tag_id', 'position']);

    $this->putJson("/lathe/api/resources/products/2/tags/{$row->tag_id}/pivot", ['pivot' => ['position' => (int) $row->position + 5]])->assertOk();

    expect($before)->toBeInt()->and(cardValue('tag-position-sum'))->toBe($before + 5);
});

it('flushes a card by a tag its calculate() names', function () {
    $this->actingAs(cacheUser());
    cardValue('tagged-count');

    expect(queriesOf(fn () => cardValue('tagged-count')))->toHaveCount(0);

    app(WidgetCache::class)->flushTables(['taggables']);

    expect(queriesOf(fn () => cardValue('tagged-count')))->toHaveCount(1);
});

it('shows the rows an action changed on the next request', function () {
    $this->actingAs(cacheUser());
    $before = cardValue('cancelled-orders');

    $this->postJson('/lathe/api/resources/orders/action', [
        'action' => 'cancel-order',
        'selection' => ['mode' => 'ids', 'ids' => [liveOrder()['id']]],
        'fields' => [],
    ])->assertOk();

    expect($before)->toBeInt()->and(cardValue('cancelled-orders'))->toBe($before + 1);
});

it('shows imported rows on the next request', function () {
    Storage::fake('local');
    $admin = cacheUser();
    $this->actingAs($admin);
    $before = cardValue('value-products', 'ALL');

    $file = 'lathe-imports/'.Str::ulid().'.csv';
    Storage::disk('local')->put($file, "Name,SKU,Price\nTrail Mug,SKU-90001,12.50\nCamp Kettle,SKU-90002,34.00\n");
    $import = Import::create([
        'user_id' => (string) $admin->id,
        'file_disk' => 'local',
        'file_name' => $file,
        'original_name' => 'products.csv',
        'model_class' => Product::class,
        'column_map' => [0 => 'name', 1 => 'sku', 2 => 'price'],
    ]);

    (new ProcessImport($import, ImportProducts::make(), new JobContext($admin->id, 'web', 'en', 'UTC')))->handle();

    expect($before)->toBeInt()->and(cardValue('value-products', 'ALL'))->toBe($before + 2);
});

it('keeps one entry per user unless the card caches across users', function () {
    $url = '/lathe/api/dashboards/cards/widgets/value-orders';
    [$admin, $editor] = [cacheUser(), cacheUser('editor@lathephp.com')];
    $this->actingAs($admin)->getJson($url)->assertOk();

    expect(queriesOf(fn () => $this->actingAs($editor)->getJson($url)->assertOk()))->toHaveCount(2);

    Cards::$widgets = [(new ValueOrders)->cacheAcrossUsers()];
    $this->travel(61)->seconds();
    $this->actingAs($admin)->getJson($url)->assertOk();

    expect(queriesOf(fn () => $this->actingAs($editor)->getJson($url)->assertOk()))->toHaveCount(0);
});

it('caches for the time to live on a store without tags', function () {
    config(['cache.default' => 'file', 'cache.stores.file.path' => sys_get_temp_dir().'/lathe-widget-cache-'.Str::random(8)]);
    // Seeded orders sit at fixed times after midnight, so at some times of day one leaves the
    // 30-day window during the 61 seconds below; at noon none does.
    $this->travelTo(today()->setTime(12, 0));
    $this->actingAs(cacheUser());
    $before = cardValue('value-orders');

    $this->postJson('/lathe/api/resources/orders', [
        'number' => 'CACHE-0002',
        'status' => 'pending',
        'placed_at' => now()->format('Y-m-d H:i:s'),
        'total_cents' => 12500,
        'customer' => ['value' => 1],
    ])->assertCreated();

    expect(cardValue('value-orders'))->toBe($before);

    $this->travel(61)->seconds();
    expect($before)->toBeInt()->and(cardValue('value-orders'))->toBe($before + 1);
});

it('runs the same 2 queries for a card on a dashboard of 3 cards and of 11, and 0 from the cache', function () {
    $this->actingAs(cacheUser());
    $url = '/lathe/api/dashboards/cards/widgets/value-orders';

    Cards::$widgets = [new ValueOrders, new ValueCustomers, new ValuePosts];
    expect(queriesOf(fn () => $this->getJson($url)->assertOk()))->toHaveCount(2)
        ->and(queriesOf(fn () => $this->getJson($url)->assertOk()))->toHaveCount(0);

    app(WidgetCache::class)->flushTables(['orders']);
    Cards::$widgets = [
        new ValueOrders, new ValueCustomers, new ValuePosts, new ValueProducts, new CancelledOrders, new TaggedCount,
        new TtlOrders, new OrderCount, new AttributedCard, new FluentCard, new TrendProbe,
    ];
    expect(Cards::$widgets)->toHaveCount(11)
        ->and(queriesOf(fn () => $this->getJson($url)->assertOk()))->toHaveCount(2)
        ->and(queriesOf(fn () => $this->getJson($url)->assertOk()))->toHaveCount(0);
});
