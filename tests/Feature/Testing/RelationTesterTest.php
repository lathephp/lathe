<?php

use Illuminate\Support\Facades\Gate;
use Lathe\Testing\LatheTest;
use Workbench\App\Models\Tag;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('reads a relation index the same as the endpoint', function () {
    $kit = LatheTest::resource('orders')->relation('items', 12)->index(['sort' => '-quantity'])->assertOk()->json('rows');
    $endpoint = $this->getJson('/lathe/api/resources/orders/12/items?sort=-quantity')->assertOk()->json('rows');

    expect(array_column($kit, 'related_id'))->toBe(array_column($endpoint, 'related_id'))
        ->and($kit)->not->toBe([]);
});

it('attaches, edits the pivot of and detaches a tag with explicit arguments', function () {
    $sale = Tag::where('name', 'sale')->value('id');
    $tags = LatheTest::resource('products')->relation('tags', 1);

    $tags->attach($sale, ['position' => 1])->assertCreated();
    expect($tags->pivotFieldValues($sale)->assertOk()->json('fields.0.value'))->toBe(1);

    $tags->updatePivot($sale, ['position' => 2])->assertOk();
    expect($tags->pivotFieldValues($sale)->json('fields.0.value'))->toBe(2)
        ->and(array_column($tags->pivotFields()->assertOk()->json('fields'), 'key'))->toBe(['position']);

    $tags->detach($sale)->assertOk();
    expect(collect($tags->index()->json('rows'))->pluck('related_id'))->not->toContain($sale);
});
