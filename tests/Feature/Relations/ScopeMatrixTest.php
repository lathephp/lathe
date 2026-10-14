<?php

namespace Lathe\Tests\Feature\Relations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lathe\Lathe;
use Lathe\Tests\Fixtures\Models\Matrix\MatrixChildren;
use Lathe\Tests\Fixtures\Models\Matrix\MatrixDetails;
use Lathe\Tests\Fixtures\Models\Matrix\MatrixGrandchildren;
use Lathe\Tests\Fixtures\Models\Matrix\MatrixNotes;
use Lathe\Tests\Fixtures\Models\Matrix\MatrixParents;
use Lathe\Tests\Fixtures\Models\Matrix\MatrixPinnedNotes;
use Lathe\Tests\Fixtures\Models\Matrix\MatrixPolicy;
use Lathe\Tests\Fixtures\Models\Matrix\MatrixSchema;
use Lathe\Tests\Fixtures\Models\Matrix\MatrixTags;
use Lathe\Tests\Fixtures\Models\Matrix\MxChild;
use Lathe\Tests\Fixtures\Models\Matrix\MxParent;
use Lathe\Tests\Fixtures\Models\Matrix\MxTag;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

/*
 * MxParent has a team global scope (team 1). A parent of team 2 is outside it, so every
 * route that names it answers 404 as if it did not exist, whatever the policy says.
 * A soft-deleted parent is outside the default query too, yet a child still names it.
 */

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    MatrixSchema::create();
    MatrixPolicy::$denied = [];
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(MatrixParents::class, MatrixChildren::class, MatrixGrandchildren::class, MatrixDetails::class, MatrixNotes::class, MatrixPinnedNotes::class, MatrixTags::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

function scopeInside(): MxParent
{
    return MxParent::query()->create(['name' => 'Inside']);
}

function scopeOutside(): MxParent
{
    return MxParent::query()->create(['name' => 'Outside', 'team' => 2]);
}

it('answers 404 to a picker that asks for a parent outside the scope as current', function () {
    $inside = scopeInside();
    $outside = scopeOutside();
    $child = MxChild::query()->create(['mx_parent_id' => $inside->id, 'name' => 'Kid']);

    $this->getJson("/lathe/api/resources/mx-children/associatable/parent?current={$inside->id}")->assertOk()->assertJsonPath('items.0.display', 'Inside');
    $this->getJson("/lathe/api/resources/mx-children/associatable/parent?current={$outside->id}")->assertNotFound();
    $this->getJson("/lathe/api/resources/mx-children/{$child->id}/parent/associatable?current={$outside->id}")->assertNotFound();
});

it('answers 404 to the list of a parent outside the scope', function () {
    $inside = scopeInside();
    $outside = scopeOutside();

    $this->getJson("/lathe/api/resources/mx-parents/{$inside->id}/children")->assertOk();
    $this->getJson("/lathe/api/resources/mx-parents/{$outside->id}/children")->assertNotFound();
});

it('answers 404 to attach, detach and update pivot on a parent outside the scope', function (string $relationship, string $table, string $column, array $morph) {
    $outside = scopeOutside();
    $tag = MxTag::query()->create(['name' => 'red']);
    DB::table($table)->insert([$column => $outside->id, 'mx_tag_id' => $tag->id, 'position' => 1, ...$morph]);
    $url = "/lathe/api/resources/mx-parents/{$outside->id}/{$relationship}";

    $this->postJson($url, ['related_id' => MxTag::query()->create(['name' => 'blue'])->id, 'pivot' => ['position' => 2]])->assertNotFound();
    $this->putJson("{$url}/{$tag->id}/pivot", ['pivot' => ['position' => 9]])->assertNotFound();
    $this->deleteJson("{$url}/{$tag->id}")->assertNotFound();

    expect(DB::table($table)->get()->map(fn (object $row) => [$row->mx_tag_id, $row->position])->all())->toBe([[$tag->id, 1]]);
})->with([
    'belongsToMany' => ['tags', 'mx_parent_tag', 'mx_parent_id', []],
    'morphToMany' => ['labels', 'mx_taggables', 'taggable_id', ['taggable_type' => MxParent::class]],
]);

it('lets attach, detach and update pivot through on a parent inside the scope', function () {
    $inside = scopeInside();
    $tag = MxTag::query()->create(['name' => 'red']);
    $url = "/lathe/api/resources/mx-parents/{$inside->id}/tags";

    $this->postJson($url, ['related_id' => $tag->id, 'pivot' => ['position' => 2]])->assertCreated();
    $this->putJson("{$url}/{$tag->id}/pivot", ['pivot' => ['position' => 9]])->assertOk();
    expect(DB::table('mx_parent_tag')->value('position'))->toBe(9);
    $this->deleteJson("{$url}/{$tag->id}")->assertOk();
    expect(DB::table('mx_parent_tag')->count())->toBe(0);
});

it('shows the name of a soft-deleted parent on the child index and detail', function () {
    $parent = scopeInside();
    $child = MxChild::query()->create(['mx_parent_id' => $parent->id, 'name' => 'Kid']);
    $parent->delete();

    $cell = $this->getJson('/lathe/api/resources/mx-children')->assertOk()->json('table.rows.0.cells.parent');
    $detail = collect($this->get("/lathe/resources/mx-children/{$child->id}")->assertOk()->viewData('page')['props']['record']['fields'] ?? [])->firstWhere('key', 'parent');

    expect($cell['display'])->toBe('Inside')
        ->and($detail['display'] ?? null)->toBe('Inside');
});

it('keeps a parent hidden by the team scope hidden on the child index and detail', function () {
    $parent = scopeOutside();
    $child = MxChild::query()->create(['mx_parent_id' => $parent->id, 'name' => 'Kid']);

    $cell = $this->getJson('/lathe/api/resources/mx-children')->assertOk()->json('table.rows.0.cells.parent');
    $detail = collect($this->get("/lathe/resources/mx-children/{$child->id}")->assertOk()->viewData('page')['props']['record']['fields'] ?? [])->firstWhere('key', 'parent');

    expect($cell)->toBe(['value' => null, 'display' => ''])
        ->and([$detail['value'], $detail['display']])->toBe([null, '']);
});
