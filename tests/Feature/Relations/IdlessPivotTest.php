<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

// The workbench's collection_product has no id column: collection 1 holds products 1 to 10, product 3 at position 3.

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

function collectionProductRows(int $product): array
{
    return DB::table('collection_product')->where('collection_id', 1)->where('product_id', $product)->pluck('position')->all();
}

it('sends no pivot_id on the rows of a pivot table with no id column', function () {
    $rows = $this->getJson('/lathe/api/resources/collections/1/products')->assertOk()->json('rows');

    expect($rows)->toHaveCount(10)
        ->and(collect($rows)->filter(fn (array $row) => array_key_exists('pivot_id', $row))->all())->toBe([]);
});

it('opens, saves and detaches a row of a pivot table with no id column by its related key', function () {
    $fields = $this->getJson('/lathe/api/resources/collections/1/products/3/pivot-fields')->assertOk()->json('fields');
    expect(collect($fields)->firstWhere('attribute', 'position')['value'])->toBe(3);

    $this->putJson('/lathe/api/resources/collections/1/products/3/pivot', ['pivot' => ['position' => 5]])->assertOk();
    expect(collectionProductRows(3))->toBe([5]);

    $this->deleteJson('/lathe/api/resources/collections/1/products/3')->assertOk()->assertExactJson(['detached' => true]);
    expect(collectionProductRows(3))->toBe([])
        ->and(DB::table('collection_product')->where('collection_id', 1)->count())->toBe(9);
});

it('ignores a pivot_id sent for a pivot table with no id column', function () {
    $this->getJson('/lathe/api/resources/collections/1/products/3/pivot-fields?pivot_id=null')->assertOk();
    $this->deleteJson('/lathe/api/resources/collections/1/products/3?pivot_id=null')->assertOk();

    expect(collectionProductRows(3))->toBe([]);
});

it('refuses to detach or update a record attached twice to a pivot table with no id column', function () {
    DB::table('collection_product')->insert(['collection_id' => 1, 'product_id' => 3, 'position' => 7]);

    $this->deleteJson('/lathe/api/resources/collections/1/products/3')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['related_id' => 'This record is attached more than once.']);
    $this->putJson('/lathe/api/resources/collections/1/products/3/pivot', ['pivot' => ['position' => 5]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['related_id' => 'This record is attached more than once.']);

    expect(collectionProductRows(3))->toBe([3, 7]);
});
