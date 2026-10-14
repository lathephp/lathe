<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Lathe\Events\ResourceAttached;
use Lathe\Events\ResourceAttachmentUpdated;
use Lathe\Events\ResourceDetached;
use Workbench\App\Lathe\Resources\Product;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('fires one event per attach, pivot update and detach', function () {
    Event::fake([ResourceAttached::class, ResourceAttachmentUpdated::class, ResourceDetached::class]);

    $this->postJson('/lathe/api/resources/products/1/tags', ['related_id' => 1, 'pivot' => ['position' => 1]])->assertCreated();

    Event::assertDispatchedTimes(ResourceAttached::class, 1);
    Event::assertDispatched(ResourceAttached::class, fn (ResourceAttached $event) => $event->parent->getKey() === 1
        && $event->resource === Product::class
        && $event->relationship === 'tags'
        && $event->ids === [1]
        && $event->pivot === ['position' => 1]);

    $this->putJson('/lathe/api/resources/products/1/tags/1/pivot', ['pivot' => ['position' => 2]])->assertOk();

    Event::assertDispatchedTimes(ResourceAttachmentUpdated::class, 1);
    Event::assertDispatched(ResourceAttachmentUpdated::class, fn (ResourceAttachmentUpdated $event) => $event->parent->getKey() === 1
        && $event->relationship === 'tags'
        && $event->ids === [1]
        && $event->original === ['position' => 1]
        && $event->changes === ['position' => 2]);

    $this->deleteJson('/lathe/api/resources/products/1/tags/1')->assertOk();

    Event::assertDispatchedTimes(ResourceDetached::class, 1);
    Event::assertDispatched(ResourceDetached::class, fn (ResourceDetached $event) => $event->parent->getKey() === 1
        && $event->relationship === 'tags'
        && $event->ids === [1]);
});

it('fires each event inside the write\'s transaction', function () {
    $levels = [];
    Event::listen(ResourceAttached::class, function () use (&$levels) {
        $levels[] = 'attached@'.DB::transactionLevel();
    });
    Event::listen(ResourceAttachmentUpdated::class, function () use (&$levels) {
        $levels[] = 'updated@'.DB::transactionLevel();
    });
    Event::listen(ResourceDetached::class, function () use (&$levels) {
        $levels[] = 'detached@'.DB::transactionLevel();
    });

    $this->postJson('/lathe/api/resources/products/1/tags', ['related_id' => 1, 'pivot' => ['position' => 1]])->assertCreated();
    $this->putJson('/lathe/api/resources/products/1/tags/1/pivot', ['pivot' => ['position' => 2]])->assertOk();
    $this->deleteJson('/lathe/api/resources/products/1/tags/1')->assertOk();

    expect($levels)->toBe(['attached@1', 'updated@1', 'detached@1']);
});

it('rolls the attach back when a ResourceAttached listener throws', function () {
    Event::listen(ResourceAttached::class, fn () => throw new RuntimeException('listener failed'));

    $this->postJson('/lathe/api/resources/products/1/tags', ['related_id' => 1, 'pivot' => ['position' => 1]])->assertStatus(500);

    expect(DB::table('taggables')->where('taggable_id', 1)->where('tag_id', 1)->count())->toBe(0);
});

it('fires no event for a denied attach or detach', function () {
    Event::fake([ResourceAttached::class, ResourceAttachmentUpdated::class, ResourceDetached::class]);
    $this->actingAs(User::where('email', 'editor@lathephp.com')->firstOrFail());

    $this->postJson('/lathe/api/resources/products/1/tags', ['related_id' => 1, 'pivot' => ['position' => 1]])->assertForbidden();
    $this->putJson('/lathe/api/resources/products/1/tags/4/pivot', ['pivot' => ['position' => 2]])->assertForbidden();
    $this->deleteJson('/lathe/api/resources/products/1/tags/4')->assertForbidden();

    Event::assertNotDispatched(ResourceAttached::class);
    Event::assertNotDispatched(ResourceAttachmentUpdated::class);
    Event::assertNotDispatched(ResourceDetached::class);
});
