<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Tests\Fixtures\Dashboards\Cards;
use Lathe\Tests\Fixtures\Widgets\OrderCount;
use Lathe\Tests\Fixtures\Widgets\TrendProbe;
use Lathe\Tests\Fixtures\Widgets\ValueOrders;
use Lathe\Tests\Fixtures\Widgets\ValueProbe;
use Lathe\Widgets\AggregateQuery;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Cards::$widgets = [(new ValueOrders)->cacheAcrossUsers()];
    Lathe::registerDashboards(Cards::class);
    config(['inertia.testing.ensure_pages_exist' => false]);
});

function linkUser(string $email): User
{
    return User::where('email', $email)->firstOrFail();
}

it('points a card that counts orders at the orders index', function () {
    $this->actingAs(linkUser('admin@lathephp.com'));

    expect($this->getJson('/lathe/api/dashboards/cards/widgets/value-orders?range=30')->json())
        ->toMatchArray(['link' => '/lathe/resources/orders'])
        ->not->toHaveKey('_source');
});

it('leaves the link off for a user who may not list orders, after the admin warmed the shared cache', function () {
    Gate::before(fn (User $user, string $ability) => $ability === 'viewAny' && $user->email === 'editor@lathephp.com' ? false : null);

    $this->actingAs(linkUser('admin@lathephp.com'))->getJson('/lathe/api/dashboards/cards/widgets/value-orders?range=30')->assertJsonPath('link', '/lathe/resources/orders');

    expect($this->actingAs(linkUser('editor@lathephp.com'))->getJson('/lathe/api/dashboards/cards/widgets/value-orders?range=30')->assertOk()->json())
        ->not->toHaveKey('link')
        ->not->toHaveKey('_source');
});

it('carries the link a card declares', function () {
    Cards::$widgets = [(new OrderCount)->link('/somewhere')];

    $this->actingAs(linkUser('admin@lathephp.com'))
        ->getJson('/lathe/api/dashboards/cards/widgets/order-count')
        ->assertJsonPath('link', '/somewhere');
});

it('opens the orders placed in the card\'s days, as many as the card counts', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-03 15:00:00', 'UTC'));
    DB::table('orders')->limit(6)->update(['placed_at' => '2026-10-28 00:30:00']);
    DB::table('orders')->whereNotIn('placed_at', ['2026-10-28 00:30:00'])->limit(3)->update(['placed_at' => '2026-10-27 23:30:00']);
    ValueProbe::$using = fn (LatheRequest $request) => $this->count($request, Order::class, null, 'placed_at');
    Cards::$widgets = [new ValueProbe];
    $this->actingAs(linkUser('admin@lathephp.com'));

    $card = $this->getJson('/lathe/api/dashboards/cards/widgets/value-probe?range=7')->assertOk()->json();
    $query = Str::after($card['link'], '?');

    expect($card['link'])->toBe('/lathe/resources/orders?'.http_build_query(['filter' => ['placed' => ['from' => '2026-10-28', 'to' => '2026-11-03']]]))
        ->and($this->getJson('/lathe/api/resources/orders/count?'.$query)->json('value'))->toBe($card['value']['value']);
});

it('opens the trend\'s days too, and the plain list for a trend counted by the hour', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-03 15:00:00', 'UTC'));
    TrendProbe::$using = fn (LatheRequest $request) => $this->countByDays($request, Order::class, null, 'placed_at');
    Cards::$widgets = [new TrendProbe];
    $this->actingAs(linkUser('admin@lathephp.com'));

    $this->getJson('/lathe/api/dashboards/cards/widgets/trend-probe?range=30')
        ->assertJsonPath('link', '/lathe/resources/orders?'.http_build_query(['filter' => ['placed' => ['from' => '2026-10-05', 'to' => '2026-11-03']]]));

    TrendProbe::$using = fn (LatheRequest $request) => $this->countByHours($request, Order::class, null, 'placed_at');

    $this->getJson('/lathe/api/dashboards/cards/widgets/trend-probe?range=24')->assertJsonPath('link', '/lathe/resources/orders');
});

it('keeps the plain link for a card dated by a column no date filter reads', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-03 15:00:00', 'UTC'));
    ValueProbe::$using = fn (LatheRequest $request) => $this->count($request, Order::class, null, 'updated_at');
    Cards::$widgets = [new ValueProbe];

    $this->actingAs(linkUser('admin@lathephp.com'))
        ->getJson('/lathe/api/dashboards/cards/widgets/value-probe?range=7')
        ->assertJsonPath('link', '/lathe/resources/orders');
});

it('keeps the link a dated card declares', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-03 15:00:00', 'UTC'));
    ValueProbe::$using = fn (LatheRequest $request) => $this->count($request, Order::class, null, 'placed_at');
    Cards::$widgets = [(new ValueProbe)->link('/somewhere')];

    $this->actingAs(linkUser('admin@lathephp.com'))
        ->getJson('/lathe/api/dashboards/cards/widgets/value-probe?range=7')
        ->assertJsonPath('link', '/somewhere');
});

it('lists what a day trend counted in a zone ahead of UTC, near its midnight', function () {
    Lathe::userTimezone(fn () => 'Asia/Dhaka');
    $this->travelTo(CarbonImmutable::parse('2026-11-03 15:00:00', 'Asia/Dhaka'));
    // 00:30 on Oct 28 in Dhaka is still Oct 27 in UTC; 23:30 on Oct 27 in Dhaka is outside the 7 days.
    DB::table('orders')->limit(4)->update(['placed_at' => '2026-10-27 18:30:00']);
    DB::table('orders')->whereNotIn('placed_at', ['2026-10-27 18:30:00'])->limit(3)->update(['placed_at' => '2026-10-27 17:30:00']);
    TrendProbe::$using = fn (LatheRequest $request) => $this->countByDays($request, Order::class, null, 'placed_at');
    Cards::$widgets = [new TrendProbe];
    $this->actingAs(linkUser('admin@lathephp.com'));

    $card = $this->getJson('/lathe/api/dashboards/cards/widgets/trend-probe?range=7')->assertOk()->json();
    $counted = array_sum(AggregateQuery::for(Order::class)->dateColumn('placed_at')->timezone('Asia/Dhaka')->byDays(7)->trend()->jsonSerialize());

    expect($card['link'])->toBe('/lathe/resources/orders?'.http_build_query(['filter' => ['placed' => ['from' => '2026-10-28', 'to' => '2026-11-03']]]))
        ->and($this->getJson('/lathe/api/resources/orders/count?'.Str::after($card['link'], '?'))->json('value'))->toBe($counted)
        ->and($counted)->toBeGreaterThanOrEqual(4);
});

it('lists what a day card counted on a Dhaka app, from the day the app stored', function () {
    appTimezone('Asia/Dhaka');
    Lathe::userTimezone(fn () => 'Asia/Dhaka');
    $this->travelTo(CarbonImmutable::parse('2026-11-11 23:45:00', 'Asia/Dhaka'));
    DB::table('orders')->update(['placed_at' => '2026-01-01 12:00:00']);
    DB::table('orders')->limit(2)->update(['placed_at' => '2026-11-11 23:30:00']);
    ValueProbe::$using = fn (LatheRequest $request) => $this->count($request, Order::class, null, 'placed_at');
    Cards::$widgets = [new ValueProbe];
    $this->actingAs(linkUser('admin@lathephp.com'));

    $card = $this->getJson('/lathe/api/dashboards/cards/widgets/value-probe?range=1')->assertOk()->json();

    expect($card['value']['value'])->toBe(2)
        ->and($card['link'])->toBe('/lathe/resources/orders?'.http_build_query(['filter' => ['placed' => ['from' => '2026-11-11', 'to' => '2026-11-11']]]))
        ->and($this->getJson('/lathe/api/resources/orders/count?'.Str::after($card['link'], '?'))->json('value'))->toBe(2);
});
