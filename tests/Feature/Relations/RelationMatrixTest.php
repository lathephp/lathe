<?php

namespace Lathe\Tests\Feature\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase;
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
use Lathe\Tests\Fixtures\Models\Matrix\MxDetail;
use Lathe\Tests\Fixtures\Models\Matrix\MxGrandchild;
use Lathe\Tests\Fixtures\Models\Matrix\MxNote;
use Lathe\Tests\Fixtures\Models\Matrix\MxParent;
use Lathe\Tests\Fixtures\Models\Matrix\MxTag;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

/*
 * The relation matrix: create, edit, attach, detach, validation and policy for each of the
 * ten relation kinds, over HTTP on the Matrix fixtures. A kind that does not take an
 * operation is asserted to refuse it the way the server does (404 for attach and detach on
 * a kind without a pivot, a 422 via for a through kind).
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

function mxParent(string $name = 'Alpha'): MxParent
{
    return MxParent::query()->create(['name' => $name]);
}

function mxTag(string $name = 'red'): MxTag
{
    return MxTag::query()->create(['name' => $name]);
}

function mxChild(MxParent $parent, string $name = 'Kid'): MxChild
{
    return MxChild::query()->create(['mx_parent_id' => $parent->id, 'name' => $name]);
}

/**
 * How one pivot kind reaches its tables: the owner Resource, its relationship, the
 * pivot table and the two key columns, with the morph type the table also carries.
 *
 * @return array{uri: string, relationship: string, ability: string, owner: callable(): Model, related: callable(): Model, table: string, ownerColumn: string, relatedColumn: string, morph: array<string, string>}
 */
function mxPivot(string $kind): array
{
    return match ($kind) {
        'belongsToMany' => ['uri' => 'mx-parents', 'relationship' => 'tags', 'ability' => 'Tags', 'owner' => fn () => mxParent(), 'related' => fn () => mxTag(),
            'table' => 'mx_parent_tag', 'ownerColumn' => 'mx_parent_id', 'relatedColumn' => 'mx_tag_id', 'morph' => []],
        'morphToMany' => ['uri' => 'mx-parents', 'relationship' => 'labels', 'ability' => 'Labels', 'owner' => fn () => mxParent(), 'related' => fn () => mxTag(),
            'table' => 'mx_taggables', 'ownerColumn' => 'taggable_id', 'relatedColumn' => 'mx_tag_id', 'morph' => ['taggable_type' => MxParent::class]],
        default => ['uri' => 'mx-tags', 'relationship' => 'parents', 'ability' => 'Parents', 'owner' => fn () => mxTag(), 'related' => fn () => mxParent(),
            'table' => 'mx_taggables', 'ownerColumn' => 'mx_tag_id', 'relatedColumn' => 'taggable_id', 'morph' => ['taggable_type' => MxParent::class]],
    };
}

/**
 * The pivot rows of one owner as [related id, position] pairs.
 *
 * @param  array<string, mixed>  $spec
 * @return list<array{int, int}>
 */
function mxPivotRows(array $spec, mixed $ownerId): array
{
    return DB::table($spec['table'])->where($spec['ownerColumn'], $ownerId)->where($spec['morph'])->orderBy('id')->get()
        ->map(fn (object $row) => [$row->{$spec['relatedColumn']}, $row->position])->all();
}

/**
 * @param  array<string, mixed>  $spec
 */
function mxAttached(array $spec, Model $owner, Model $related, int $position = 1): void
{
    DB::table($spec['table'])->insert([$spec['ownerColumn'] => $owner->getKey(), $spec['relatedColumn'] => $related->getKey(), 'position' => $position, ...$spec['morph']]);
}

/**
 * The cells of the three pivot kinds, which differ only in the tables they reach.
 *
 * @return array<string, callable(TestCase): mixed>
 */
function mxPivotCases(string $kind): array
{
    $spec = mxPivot($kind);
    $url = fn (Model $owner) => "/lathe/api/resources/{$spec['uri']}/{$owner->getKey()}/{$spec['relationship']}";

    return [
        'create' => function ($t) use ($spec) {
            $related = ($spec['related'])();

            $id = $t->postJson("/lathe/api/resources/{$spec['uri']}", [
                'name' => 'Fresh',
                $spec['relationship'] => [$related->getKey()],
                $spec['relationship'].'_pivot' => [(string) $related->getKey() => ['position' => 2]],
            ])->assertCreated()->json('id');

            expect(mxPivotRows($spec, $id))->toBe([[$related->getKey(), 2]]);
        },
        'edit' => function ($t) use ($spec) {
            $owner = ($spec['owner'])();
            $related = ($spec['related'])();
            mxAttached($spec, $owner, $related, 1);

            $t->putJson("/lathe/api/resources/{$spec['uri']}/{$owner->getKey()}", [
                'name' => 'Renamed',
                $spec['relationship'] => [$related->getKey()],
                $spec['relationship'].'_pivot' => [(string) $related->getKey() => ['position' => 5]],
            ])->assertOk();

            expect(mxPivotRows($spec, $owner->getKey()))->toBe([[$related->getKey(), 5]]);
        },
        'attach' => function ($t) use ($spec, $url) {
            $owner = ($spec['owner'])();
            $related = ($spec['related'])();

            $t->postJson($url($owner), ['related_id' => $related->getKey(), 'pivot' => ['position' => 3]])
                ->assertCreated()
                ->assertExactJson(['attached' => true]);

            expect(mxPivotRows($spec, $owner->getKey()))->toBe([[$related->getKey(), 3]]);
        },
        'detach' => function ($t) use ($spec, $url) {
            $owner = ($spec['owner'])();
            $related = ($spec['related'])();
            mxAttached($spec, $owner, $related);

            $t->deleteJson($url($owner).'/'.$related->getKey())
                ->assertOk()
                ->assertExactJson(['detached' => true]);

            expect(mxPivotRows($spec, $owner->getKey()))->toBe([]);
        },
        'validation' => function ($t) use ($spec, $url) {
            $owner = ($spec['owner'])();
            $related = ($spec['related'])();

            $t->postJson($url($owner), ['related_id' => $related->getKey(), 'pivot' => []])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['pivot.position']);

            expect(mxPivotRows($spec, $owner->getKey()))->toBe([]);
        },
        'policy' => function ($t) use ($spec, $url) {
            $owner = ($spec['owner'])();
            $related = ($spec['related'])();
            MatrixPolicy::$denied = ['attach'.$spec['ability']];

            $t->postJson($url($owner), ['related_id' => $related->getKey(), 'pivot' => ['position' => 3]])->assertForbidden();

            expect(mxPivotRows($spec, $owner->getKey()))->toBe([]);
        },
    ];
}

/**
 * The attach and detach cells of a kind without a pivot: the server answers 404.
 *
 * @return array<string, callable(TestCase): mixed>
 */
function mxNoPivotCases(string $relationship): array
{
    return [
        'attach' => function ($t) use ($relationship) {
            $parent = mxParent();

            $t->postJson("/lathe/api/resources/mx-parents/{$parent->id}/{$relationship}", ['related_id' => 1])->assertNotFound();
        },
        'detach' => function ($t) use ($relationship) {
            $parent = mxParent();

            $t->deleteJson("/lathe/api/resources/mx-parents/{$parent->id}/{$relationship}/1")->assertNotFound();
        },
    ];
}

/**
 * The cells of a read-only through kind.
 *
 * @return array<string, callable(TestCase): mixed>
 */
function mxThroughCases(string $relationship): array
{
    $seed = function (): array {
        $parent = mxParent();
        $grandchild = MxGrandchild::query()->create(['mx_child_id' => mxChild($parent)->id, 'name' => 'Grand']);

        return [$parent, $grandchild];
    };

    return [
        ...mxNoPivotCases($relationship),
        'create' => function ($t) use ($seed, $relationship) {
            [$parent] = $seed();

            $t->postJson("/lathe/api/resources/mx-grandchildren?via=mx-parents/{$parent->id}/{$relationship}", ['name' => 'Other'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['via']);

            expect(MxGrandchild::query()->count())->toBe(1);
        },
        'edit' => function ($t) use ($seed, $relationship) {
            [$parent] = $seed();

            $t->putJson("/lathe/api/resources/mx-parents/{$parent->id}", ['name' => 'Renamed', $relationship => [['name' => 'Other']]])->assertOk();

            expect($parent->fresh()?->name)->toBe('Renamed')
                ->and(MxGrandchild::query()->pluck('name')->all())->toBe(['Grand']);
        },
        'validation' => function ($t) use ($seed, $relationship) {
            [$parent, $grandchild] = $seed();

            $t->putJson("/lathe/api/resources/mx-parents/{$parent->id}/{$relationship}/{$grandchild->id}/pivot", ['pivot' => ['position' => 'x']])->assertNotFound();
        },
        'policy' => function ($t) use ($seed, $relationship) {
            [$parent] = $seed();

            expect($t->getJson("/lathe/api/resources/mx-parents/{$parent->id}/{$relationship}")->assertOk()->json('rows'))->toHaveCount(1);

            MatrixPolicy::$denied = ['viewAnyGrandchildren'];

            $t->getJson("/lathe/api/resources/mx-parents/{$parent->id}/{$relationship}")->assertForbidden();
        },
    ];
}

/**
 * The matrix cells, keyed by kind and operation. Each runs against the test case.
 *
 * @return array<string, array<string, callable(TestCase): mixed>>
 */
function mxCases(): array
{
    return [
        'belongsTo' => [
            'create' => function ($t) {
                $parent = mxParent();

                $t->postJson('/lathe/api/resources/mx-children', ['name' => 'Kid', 'parent' => ['value' => $parent->id]])->assertCreated();

                expect(MxChild::query()->sole()->mx_parent_id)->toBe($parent->id);
            },
            'edit' => function ($t) {
                $first = mxParent('First');
                $second = mxParent('Second');
                $child = mxChild($first);

                $t->putJson("/lathe/api/resources/mx-children/{$child->id}", ['name' => 'Kid', 'parent' => ['value' => $second->id]])->assertOk();

                expect($child->fresh()?->mx_parent_id)->toBe($second->id);
            },
            'attach' => function ($t) {
                $parent = mxParent();
                $child = mxChild($parent);

                $t->postJson("/lathe/api/resources/mx-children/{$child->id}/parent", ['related_id' => $parent->id])->assertNotFound();
            },
            'detach' => function ($t) {
                $parent = mxParent();
                $child = mxChild($parent);

                $t->deleteJson("/lathe/api/resources/mx-children/{$child->id}/parent/{$parent->id}")->assertNotFound();

                expect($child->fresh()?->mx_parent_id)->toBe($parent->id);
            },
            'validation' => function ($t) {
                $response = $t->postJson('/lathe/api/resources/mx-children', ['name' => 'Kid', 'parent' => ['value' => 999999]])->assertUnprocessable();

                expect($response->json('errors.parent.0'))->toEndWith('must be a valid relation.')
                    ->and(MxChild::query()->count())->toBe(0);
            },
            'policy' => function ($t) {
                $parent = mxParent();
                MatrixPolicy::$denied = ['addParent'];

                $response = $t->postJson('/lathe/api/resources/mx-children', ['name' => 'Kid', 'parent' => ['value' => $parent->id]])->assertUnprocessable();

                expect($response->json('errors.parent.0'))->toBe('You are not authorized to relate this resource.')
                    ->and(MxChild::query()->count())->toBe(0);
            },
        ],
        'hasOne' => [
            ...mxNoPivotCases('detail'),
            'create' => function ($t) {
                $id = $t->postJson('/lathe/api/resources/mx-parents', ['name' => 'Alpha', 'detail' => ['body' => 'First']])->assertCreated()->json('id');

                expect(MxDetail::query()->sole()->only(['mx_parent_id', 'body']))->toBe(['mx_parent_id' => $id, 'body' => 'First']);
            },
            'edit' => function ($t) {
                $parent = mxParent();
                $detail = MxDetail::query()->create(['mx_parent_id' => $parent->id, 'body' => 'First']);

                $t->putJson("/lathe/api/resources/mx-parents/{$parent->id}", ['name' => 'Alpha', 'detail' => ['body' => 'Second']])->assertOk();

                expect(MxDetail::query()->sole()->only(['id', 'body']))->toBe(['id' => $detail->id, 'body' => 'Second']);
            },
            'validation' => function ($t) {
                $t->postJson('/lathe/api/resources/mx-parents', ['name' => 'Alpha', 'detail' => ['body' => str_repeat('x', 21)]])
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors(['detail.body']);

                expect(MxParent::query()->count())->toBe(0);
            },
            'policy' => function ($t) {
                $parent = mxParent();
                MatrixPolicy::$denied = ['addDetail'];

                $t->postJson("/lathe/api/resources/mx-details?via=mx-parents/{$parent->id}/detail", ['body' => 'First', 'parent' => ['value' => $parent->id]])->assertForbidden();

                expect(MxDetail::query()->count())->toBe(0);
            },
        ],
        'hasMany' => [
            ...mxNoPivotCases('children'),
            'create' => function ($t) {
                $parent = mxParent();

                $t->postJson("/lathe/api/resources/mx-children?via=mx-parents/{$parent->id}/children", ['name' => 'Kid', 'parent' => ['value' => $parent->id]])->assertCreated();

                expect($parent->children()->pluck('name')->all())->toBe(['Kid']);
            },
            'edit' => function ($t) {
                $parent = mxParent();
                $child = mxChild($parent);

                $t->putJson("/lathe/api/resources/mx-children/{$child->id}", ['name' => 'Renamed', 'parent' => ['value' => $parent->id]])->assertOk();

                expect($t->getJson("/lathe/api/resources/mx-parents/{$parent->id}/children")->assertOk()->json('rows'))->toHaveCount(1)
                    ->and($parent->children()->pluck('name')->all())->toBe(['Renamed']);
            },
            'validation' => function ($t) {
                $parent = mxParent();

                $t->postJson("/lathe/api/resources/mx-children?via=mx-parents/{$parent->id}/nope", ['name' => 'Kid', 'parent' => ['value' => $parent->id]])
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors(['via']);

                expect(MxChild::query()->count())->toBe(0);
            },
            'policy' => function ($t) {
                $parent = mxParent();
                MatrixPolicy::$denied = ['addChildren'];

                $t->postJson("/lathe/api/resources/mx-children?via=mx-parents/{$parent->id}/children", ['name' => 'Kid', 'parent' => ['value' => $parent->id]])->assertForbidden();

                expect(MxChild::query()->count())->toBe(0);
            },
        ],
        'belongsToMany' => mxPivotCases('belongsToMany'),
        'morphMany' => [
            ...mxNoPivotCases('notes'),
            'create' => function ($t) {
                $parent = mxParent();

                $t->postJson("/lathe/api/resources/mx-notes?via=mx-parents/{$parent->id}/notes", ['body' => 'First', 'notable' => ['type' => 'mx-parents', 'id' => $parent->id]])->assertCreated();

                expect(MxNote::query()->sole()->only(['notable_type', 'notable_id', 'body']))->toBe(['notable_type' => MxParent::class, 'notable_id' => $parent->id, 'body' => 'First']);
            },
            'edit' => function ($t) {
                $parent = mxParent();
                $note = $parent->notes()->create(['body' => 'First']);

                $t->putJson("/lathe/api/resources/mx-notes/{$note->id}", ['body' => 'Second', 'notable' => ['type' => 'mx-parents', 'id' => $parent->id]])->assertOk();

                expect($parent->notes()->pluck('body')->all())->toBe(['Second']);
            },
            'validation' => function ($t) {
                $parent = mxParent();

                $t->postJson("/lathe/api/resources/mx-notes?via=mx-parents/{$parent->id}/notes", ['body' => '', 'notable' => ['type' => 'mx-parents', 'id' => $parent->id]])
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors(['body']);

                expect(MxNote::query()->count())->toBe(0);
            },
            'policy' => function ($t) {
                $parent = mxParent();
                MatrixPolicy::$denied = ['addNotes'];

                $t->postJson("/lathe/api/resources/mx-notes?via=mx-parents/{$parent->id}/notes", ['body' => 'First', 'notable' => ['type' => 'mx-parents', 'id' => $parent->id]])->assertForbidden();

                expect(MxNote::query()->count())->toBe(0);
            },
        ],
        'morphOne' => [
            ...mxNoPivotCases('pinned'),
            'create' => function ($t) {
                $id = $t->postJson('/lathe/api/resources/mx-parents', ['name' => 'Alpha', 'pinned' => ['body' => 'First']])->assertCreated()->json('id');

                expect(MxNote::query()->sole()->only(['notable_type', 'notable_id', 'body']))->toBe(['notable_type' => MxParent::class, 'notable_id' => $id, 'body' => 'First']);
            },
            'edit' => function ($t) {
                $parent = mxParent();
                $note = $parent->pinned()->create(['body' => 'First']);

                $t->putJson("/lathe/api/resources/mx-parents/{$parent->id}", ['name' => 'Alpha', 'pinned' => ['body' => 'Second']])->assertOk();

                expect(MxNote::query()->sole()->only(['id', 'body']))->toBe(['id' => $note->id, 'body' => 'Second']);
            },
            'validation' => function ($t) {
                $t->postJson('/lathe/api/resources/mx-parents', ['name' => 'Alpha', 'pinned' => ['body' => str_repeat('x', 21)]])
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors(['pinned.body']);

                expect(MxParent::query()->count())->toBe(0);
            },
            'policy' => function ($t) {
                $parent = mxParent();
                MatrixPolicy::$denied = ['addPinned'];

                $t->postJson("/lathe/api/resources/mx-pinned-notes?via=mx-parents/{$parent->id}/pinned", ['body' => 'First', 'notable' => ['type' => 'mx-parents', 'id' => $parent->id]])->assertForbidden();

                expect(MxNote::query()->count())->toBe(0);
            },
        ],
        'morphToMany' => mxPivotCases('morphToMany'),
        'morphedByMany' => mxPivotCases('morphedByMany'),
        'hasOneThrough' => mxThroughCases('firstGrandchild'),
        'hasManyThrough' => mxThroughCases('grandchildren'),
    ];
}

it('runs a relation matrix case', function (string $kind, string $operation) {
    $case = mxCases()[$kind][$operation] ?? null;

    expect($case)->not->toBeNull();

    $case($this);
})->with('relation matrix');
