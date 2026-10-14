<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Lathe\Actions\Action;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Lathe;
use Lathe\Tests\Fixtures\Lenses\AllParents;
use Lathe\Tests\Fixtures\Models\Matrix\MatrixChildren;
use Lathe\Tests\Fixtures\Models\Matrix\MatrixDetails;
use Lathe\Tests\Fixtures\Models\Matrix\MatrixGrandchildren;
use Lathe\Tests\Fixtures\Models\Matrix\MatrixNotes;
use Lathe\Tests\Fixtures\Models\Matrix\MatrixPinnedNotes;
use Lathe\Tests\Fixtures\Models\Matrix\MatrixSchema;
use Lathe\Tests\Fixtures\Models\Matrix\MatrixTags;
use Lathe\Tests\Fixtures\Models\Matrix\MxParent;
use Lathe\Tests\Fixtures\Models\Matrix\MxTag;
use Lathe\Tests\Fixtures\Policies\EmptyPolicy;
use Lathe\Tests\Fixtures\Resources\ClosedParent;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    MatrixSchema::create();
    Gate::define('viewLathe', fn () => true);
    // globalSearch is a gate the app defines, not a policy method, so the workbench's is dropped: an undefined ability denies.
    forgetGate('globalSearch');
    Gate::policy(MxParent::class, EmptyPolicy::class);
    Lathe::registerResources(ClosedParent::class, MatrixChildren::class, MatrixGrandchildren::class, MatrixDetails::class, MatrixNotes::class, MatrixPinnedNotes::class, MatrixTags::class);
    $this->user = User::where('email', 'admin@lathephp.com')->firstOrFail();
    $this->actingAs($this->user);
});

/*
 * One row per check: the ability, then the request that serves it. {id} is a live record,
 * {gone} a soft deleted one and {tag} a tag. Every request is answered 403, since a policy
 * without methods leaves no way in; the ability itself is asked of the authorization too,
 * because the Resource's viewAny would answer 403 for the whole row first.
 */
dataset('abilities', function () {
    $r = '/lathe/api/resources/closed-parents';
    $selection = ['selection' => ['mode' => 'ids', 'ids' => ['{id}']]];
    $action = ['selection' => ['mode' => 'ids', 'ids' => ['{id}']], 'fields' => []];

    return [
        'viewAny' => ['viewAny', 'GET', $r, []],
        'view' => ['view', 'GET', "{$r}/{id}/peek", []],
        'create' => ['create', 'POST', $r, ['name' => 'New']],
        'update' => ['update', 'PUT', "{$r}/{id}", ['name' => 'Renamed']],
        'delete' => ['delete', 'DELETE', "{$r}/{id}", []],
        'restore' => ['restore', 'PUT', "{$r}/{gone}/restore", []],
        'forceDelete' => ['forceDelete', 'DELETE', "{$r}/{id}/force", []],
        'replicate' => ['replicate', 'GET', "{$r}/{id}/replicate-fields", []],
        'deleteAny' => ['deleteAny', 'DELETE', "{$r}/batch", $selection],
        'restoreAny' => ['restoreAny', 'PUT', "{$r}/batch/restore", $selection],
        'forceDeleteAny' => ['forceDeleteAny', 'DELETE', "{$r}/batch/force", $selection],
        'addTags' => ['addTags', 'GET', "{$r}/relate-authorization?resourceId={id}", []],
        'attachTags' => ['attachTags', 'POST', "{$r}/{id}/tags", ['related' => '{tag}']],
        'attachAnyTags' => ['attachAnyTags', 'GET', "{$r}/{id}/tags/associatable", []],
        'detachTags' => ['detachTags', 'DELETE', "{$r}/{id}/tags/{tag}", []],
        'inlineEdit' => ['inlineEdit', 'PATCH', "{$r}/{id}/field/name", ['value' => 'Renamed']],
        'export' => ['export', 'POST', "{$r}/action", ['action' => 'export', ...$action]],
        'import' => ['import', 'POST', "{$r}/action", ['action' => 'import', ...$action]],
        'viewWidget' => ['viewWidget', 'GET', "{$r}/widgets/order-count", []],
        'viewLens' => ['viewLens', 'GET', "{$r}/lenses/all-parents", []],
        'globalSearch' => ['globalSearch', 'GET', '/lathe/api/search?q=alpha', []],
        'runAction' => ['runAction', 'GET', "{$r}/actions/tag/form", []],
        'runDestructiveAction' => ['runDestructiveAction', 'GET', "{$r}/actions/purge/form", []],
    ];
});

it('denies each of the 23 checks to a policy class with no methods (pin)', function (string $ability, string $method, string $uri, array $body) {
    $parent = MxParent::create(['name' => 'Alpha']);
    $gone = MxParent::create(['name' => 'Gone']);
    $gone->delete();
    $tag = MxTag::create(['name' => 'red']);

    $fill = fn (string $text) => str_replace(['{id}', '{gone}', '{tag}'], [$parent->id, $gone->id, $tag->id], $text);
    $authorization = app(ResourceAuthorization::class);

    $allowed = match (true) {
        str_ends_with($ability, 'Any') && ! str_starts_with($ability, 'attach') && $ability !== 'viewAny' => $authorization->narrowBulk($this->user, ClosedParent::class, Str::beforeLast($ability, 'Any'), MxParent::query())->exists(),
        $ability === 'viewLens' => $authorization->allowsLens($this->user, ClosedParent::class, new AllParents),
        $ability === 'viewWidget' => $authorization->allowsWidget($this->user, ClosedParent::class, 'order-count'),
        $ability === 'globalSearch' => Gate::forUser($this->user)->allows('globalSearch'),
        $ability === 'runAction', $ability === 'runDestructiveAction' => $authorization->allows($this->user, ClosedParent::class, $ability, $parent, [Action::make()]),
        str_starts_with($ability, 'attach') && ! str_starts_with($ability, 'attachAny'), str_starts_with($ability, 'detach') => $authorization->allows($this->user, ClosedParent::class, $ability, $parent, [[$tag->id]]),
        default => $authorization->allows($this->user, ClosedParent::class, $ability, $parent),
    };

    expect($allowed)->toBeFalse();

    $this->json($method, $fill($uri), json_decode($fill((string) json_encode($body)), true))->assertForbidden();
})->with('abilities');
