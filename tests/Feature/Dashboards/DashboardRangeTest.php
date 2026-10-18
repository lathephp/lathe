<?php

use Inertia\Testing\AssertableInertia;
use Lathe\Lathe;
use Lathe\Tests\Fixtures\Dashboards\Cards;
use Lathe\Tests\Fixtures\Widgets\RangeProbe;
use Lathe\Tests\Fixtures\Widgets\ValueOrders;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    RangeProbe::$ranges = [7 => '7 Days', 30 => '30 Days', 365 => '365 Days'];
    Cards::$widgets = [new RangeProbe];
    Lathe::registerDashboards(Cards::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    // The partial reload asserts the page file again; the page tests run without a build.
    config(['inertia.testing.ensure_pages_exist' => false]);
});

it('offers 7 days, 30 days and 12 months and picks 30 days until the URL names another', function () {
    $this->get('/lathe/dashboards/cards')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('dashboard.ranges', [
            ['value' => 7, 'label' => '7 days'],
            ['value' => 30, 'label' => '30 days'],
            ['value' => 365, 'label' => '12 months'],
        ])
        ->where('dashboard.range', 30));

    $this->get('/lathe/dashboards/cards?range=365')->assertInertia(fn (AssertableInertia $page) => $page->where('dashboard.range', 365));
    $this->get('/lathe/dashboards/cards?range=60')->assertInertia(fn (AssertableInertia $page) => $page->where('dashboard.range', 30));
});

it('computes each card of the page with the range in the URL', function () {
    $this->get('/lathe/dashboards/cards?range=365')->assertInertia(fn (AssertableInertia $page) => $page
        ->loadDeferredProps(['range-probe'], fn (AssertableInertia $reload) => $reload
            ->where('widgetValue:range-probe.value', '365')
            ->missing('widgetValue:range-probe.fallbackRange')));

    $this->get('/lathe/dashboards/cards')->assertInertia(fn (AssertableInertia $page) => $page
        ->loadDeferredProps(['range-probe'], fn (AssertableInertia $reload) => $reload
            ->where('widgetValue:range-probe.value', '30')));
});

it('falls back to the default of a card that does not offer the range, and says which it used', function () {
    RangeProbe::$ranges = [30 => '30 Days', 60 => '60 Days', 365 => '365 Days'];

    $this->get('/lathe/dashboards/cards?range=7')->assertInertia(fn (AssertableInertia $page) => $page
        ->loadDeferredProps(['range-probe'], fn (AssertableInertia $reload) => $reload
            ->where('widgetValue:range-probe.value', '30')
            ->where('widgetValue:range-probe.fallbackRange', ['value' => 30, 'label' => 'Last 30 days'])));

    expect($this->getJson('/lathe/api/dashboards/cards/widgets/range-probe?range=7')->json())
        ->toMatchArray(['value' => '30', 'fallbackRange' => ['value' => 30, 'label' => 'Last 30 days']])
        ->and($this->getJson('/lathe/api/dashboards/cards/widgets/range-probe?range=60')->json())
        ->toMatchArray(['value' => '60'])->not->toHaveKey('fallbackRange');
});

it('names a fallback that is not a day count by its own label', function () {
    RangeProbe::$ranges = ['MTD' => 'Month To Date'];

    expect($this->getJson('/lathe/api/dashboards/cards/widgets/range-probe?range=7')->json())
        ->toMatchArray(['value' => 'MTD', 'fallbackRange' => ['value' => 'MTD', 'label' => 'Month To Date']]);
});

it('sends no range to a card without ranges', function () {
    RangeProbe::$ranges = [];

    expect($this->getJson('/lathe/api/dashboards/cards/widgets/range-probe?range=365')->json())
        ->toMatchArray(['value' => null])->not->toHaveKey('fallbackRange');
});

it('counts a value card over the fallback range, not the day count it was asked for', function () {
    Cards::$widgets = [new ValueOrders];

    expect($this->getJson('/lathe/api/dashboards/cards/widgets/value-orders?range=7')->json('value.value'))
        ->toBe($this->getJson('/lathe/api/dashboards/cards/widgets/value-orders?range=30')->json('value.value'));
});
