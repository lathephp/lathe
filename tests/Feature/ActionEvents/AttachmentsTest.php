<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Lathe\ActionEvents\ActionEvent;
use Lathe\Enums\ActionEventType;
use Lathe\Events\ResourceAttached;
use Workbench\App\Models\Product;
use Workbench\App\Models\Tag;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    $this->tag = Tag::query()->where('name', 'sale')->firstOrFail();
});

it('writes an attach row naming the product, the tag and the pivot', function () {
    $this->postJson('/lathe/api/resources/products/1/tags', ['related_id' => $this->tag->id, 'pivot' => ['position' => 1]])->assertCreated();

    $row = ActionEvent::sole();

    expect($row->type)->toBe(ActionEventType::Attach)
        ->and($row->actionable_type)->toBe(Product::class)
        ->and((string) $row->actionable_id)->toBe('1')
        ->and($row->target_type)->toBe(Tag::class)
        ->and((string) $row->target_id)->toBe((string) $this->tag->id)
        ->and($row->changes)->toBe(['position' => 1]);
});

it('writes an update-attached row with the pivot before and after', function () {
    $this->postJson('/lathe/api/resources/products/1/tags', ['related_id' => $this->tag->id, 'pivot' => ['position' => 1]])->assertCreated();
    $this->putJson("/lathe/api/resources/products/1/tags/{$this->tag->id}/pivot", ['pivot' => ['position' => 2]])->assertOk();

    $row = ActionEvent::query()->where('type', 'update-attached')->sole();

    expect($row->original)->toBe(['position' => 1])
        ->and($row->changes)->toBe(['position' => 2])
        ->and($row->target_type)->toBe(Tag::class);
});

it('writes a detach row for the tag', function () {
    $this->postJson('/lathe/api/resources/products/1/tags', ['related_id' => $this->tag->id, 'pivot' => ['position' => 1]])->assertCreated();
    $this->deleteJson("/lathe/api/resources/products/1/tags/{$this->tag->id}")->assertOk();

    $row = ActionEvent::query()->where('type', 'detach')->sole();

    expect((string) $row->actionable_id)->toBe('1')
        ->and($row->target_type)->toBe(Tag::class)
        ->and((string) $row->target_id)->toBe((string) $this->tag->id);
});

it('leaves no row when the attach rolls back', function () {
    Event::listen(ResourceAttached::class, fn () => throw new RuntimeException('late listener'));

    $this->postJson('/lathe/api/resources/products/1/tags', ['related_id' => $this->tag->id, 'pivot' => ['position' => 1]])->assertStatus(500);

    expect(ActionEvent::count())->toBe(0);
});
