<?php

namespace Lathe\Tests\Feature\Records;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lathe\Http\Middleware\HandleInertiaRequests;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

// Budget of 3 measured on a warmed panel: the first page of orders, its customers, the user's saved tabs.
it('runs only the first-page query when an index is prefetched', function () {
    warmNavigationBadges();

    $sql = [];
    DB::listen(function ($query) use (&$sql) {
        $sql[] = strtolower($query->sql);
    });

    $response = $this->get('/lathe/resources/orders', [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'Purpose' => 'prefetch',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
    ])->assertOk();

    expect($response->json('component'))->toBe('Resources/Index')
        ->and($response->json('deferredProps.counts'))->toEqualCanonicalizing(['count', 'tabCounts', 'summaries']);

    $orders = array_values(array_filter($sql, fn (string $query) => str_contains($query, '"orders"')));
    $countsOnOrders = array_filter($orders, fn (string $query) => str_contains($query, 'count('));

    expect($orders)->toHaveCount(1)
        ->and($orders[0])->toStartWith('select ')->and($orders[0])->toContain('limit')
        ->and($countsOnOrders)->toBeEmpty()
        ->and($sql)->toHaveCount(3);
});
