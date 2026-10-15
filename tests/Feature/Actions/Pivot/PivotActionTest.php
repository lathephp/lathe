<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Lathe\ActionEvents\ActionEvent;
use Workbench\App\Models\Product;
use Workbench\App\Models\Tag;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());

    $this->product = Product::findOrFail(1);
    $this->tags = Tag::query()->orderBy('id')->limit(4)->get();
    $this->product->tags()->detach();

    foreach ($this->tags->take(3) as $index => $tag) {
        $this->product->tags()->attach($tag->id, ['position' => $index + 5]);
    }
});

/**
 * @param  list<int>  $ids
 */
function moveToFirst(array $ids, string $via = 'products/1/tags'): TestResponse
{
    return test()->postJson('/lathe/api/resources/tags/action', [
        'action' => 'move-to-first',
        'selection' => ['mode' => 'ids', 'ids' => $ids, 'via' => $via],
        'fields' => [],
    ]);
}

/**
 * @return array<int, int>
 */
function positions(): array
{
    return DB::table('taggables')->where('taggable_type', (new Product)->getMorphClass())->where('taggable_id', 1)->orderBy('tag_id')->pluck('position', 'tag_id')->map(fn ($p) => (int) $p)->all();
}

it('lists the pivot actions of the Tags field as Tag Actions', function () {
    $json = $this->getJson('/lathe/api/resources/products/actions?display=index')->assertOk()->json('pivotActions');

    expect($json)->toHaveCount(1)
        ->and($json[0]['name'])->toBe('Tag Actions')
        ->and(array_column($json[0]['actions'], 'uriKey'))->toBe(['move-to-first']);
});

it('moves exactly the 2 ticked pivot rows of product 1 to the first position', function () {
    [$first, $second, $third] = $this->tags->take(3)->pluck('id')->all();
    $other = Product::findOrFail(2);
    $other->tags()->attach($first, ['position' => 9]);

    moveToFirst([$first, $second])->assertOk()->assertJson(['permitted' => 2, 'skipped' => 0, 'failed' => 0]);

    expect(positions())->toBe([$first => 1, $second => 1, $third => 7])
        ->and((int) DB::table('taggables')->where('taggable_id', 2)->where('tag_id', $first)->value('position'))->toBe(9)
        ->and(ActionEvent::where('type', 'action-ran')->count())->toBe(2);
});

it('skips a tag the product does not carry and never changes it', function () {
    [$first] = $this->tags->take(1)->pluck('id')->all();
    $loose = $this->tags[3]->id;

    moveToFirst([$first, $loose])->assertOk()->assertJson(['permitted' => 1, 'skipped' => 1]);

    expect(DB::table('taggables')->where('tag_id', $loose)->where('taggable_id', 1)->exists())->toBeFalse();
});

it('answers 422 for a via naming a parent the user may not view or a wrong relation', function () {
    [$first] = $this->tags->take(1)->pluck('id')->all();

    moveToFirst([$first], 'products/999999/tags')->assertUnprocessable()->assertJsonValidationErrors('selection');
    moveToFirst([$first], 'products/1/comments')->assertUnprocessable();
    moveToFirst([$first], 'nonsense')->assertUnprocessable();
});

it('offers the pivot actions on the product Tags panel', function () {
    $this->getJson('/lathe/api/resources/products/1/tags')->assertOk()
        ->assertJsonPath('pivotActions.0.uriKey', 'move-to-first');
});
