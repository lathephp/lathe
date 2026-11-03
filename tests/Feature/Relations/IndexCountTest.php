<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

// The workbench's Collections index lists Field::belongsToMany('Products')->withCount(): Summer
// picks holds 10 products, New in 3 and Clearance none.

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

afterEach(function () {
    Model::preventLazyLoading(false);
});

/**
 * Collections up to $total, each holding two products.
 */
function collectionsUpTo(int $total): void
{
    foreach (range(DB::table('collections')->count() + 1, $total) as $i) {
        $id = DB::table('collections')->insertGetId(['name' => "Collection {$i}"]);
        DB::table('collection_product')->insert([['collection_id' => $id, 'product_id' => 1], ['collection_id' => $id, 'product_id' => 2]]);
    }
}

function collectionIndexQueries(): int
{
    $count = 0;
    DB::listen(function () use (&$count) {
        $count++;
    });
    test()->getJson('/lathe/api/resources/collections?per_page=25')->assertOk();

    return $count;
}

it('lists the Products count of each collection', function () {
    $rows = $this->getJson('/lathe/api/resources/collections')->assertOk()->json('table.rows');
    $counts = collect($rows)->mapWithKeys(fn (array $row) => [$row['cells']['name']['value'] => $row['cells']['products']]);

    expect($counts->all())->toBe([
        'Clearance' => ['value' => 0, 'display' => '0'],
        'New in' => ['value' => 3, 'display' => '3'],
        'Summer picks' => ['value' => 10, 'display' => '10'],
    ]);
});

it('counts the products of a page of 25 collections in as many queries as a page of 5', function () {
    Model::preventLazyLoading();
    warmNavigationBadges();
    collectionsUpTo(5);
    $five = collectionIndexQueries();
    collectionsUpTo(25);
    $twentyFive = collectionIndexQueries();

    expect($this->getJson('/lathe/api/resources/collections?per_page=25')->json('table.rows'))->toHaveCount(25)
        ->and($twentyFive)->toBe($five);
});
