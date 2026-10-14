<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Lathe\ActionEvents\ActionEvent;
use Lathe\Enums\ActionEventType;
use Lathe\Events\ResourceUpdated;
use Workbench\App\Lathe\Resources\Customer as CustomerResource;
use Workbench\App\Lathe\Resources\Order as OrderResource;
use Workbench\App\Lathe\Resources\Post as PostResource;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\Post;
use Workbench\App\Models\Tag;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->admin = User::where('email', 'admin@lathephp.com')->firstOrFail();
    $this->actingAs($this->admin);
});

it('writes one create row with the new state as changes', function () {
    $id = $this->postJson('/lathe/api/resources/customers', ['name' => 'Ada', 'email' => 'ada@example.test', 'country' => 'US', 'type' => 'person'])
        ->assertCreated()->json('id');

    $row = ActionEvent::sole();

    expect($row->type)->toBe(ActionEventType::Create)
        ->and($row->actionable_type)->toBe(Customer::class)
        ->and((string) $row->actionable_id)->toBe((string) $id)
        ->and($row->resource_class)->toBe(CustomerResource::class)
        ->and((string) $row->user_id)->toBe((string) $this->admin->id)
        ->and($row->changes['name'])->toBe('Ada')
        ->and($row->original)->toBeNull()
        ->and($row->batch_id)->toBeString();
});

it('writes one update row holding only the keys that differ', function () {
    $customer = Customer::query()->findOrFail(1);

    $this->putJson('/lathe/api/resources/customers/1', ['name' => 'Renamed', 'email' => $customer->email, 'country' => $customer->country, 'type' => $customer->type])->assertOk();

    $row = ActionEvent::sole();

    expect($row->type)->toBe(ActionEventType::Update)
        ->and($row->original['name'])->toBe($customer->name)
        ->and($row->changes['name'])->toBe('Renamed')
        ->and($row->changes)->not->toHaveKey('email')
        ->and($row->original)->not->toHaveKey('email');
});

it('writes one delete row holding the record as original', function () {
    $tag = Tag::query()->latest('id')->firstOrFail();

    $this->deleteJson("/lathe/api/resources/tags/{$tag->id}")->assertOk();

    $row = ActionEvent::sole();

    expect($row->type)->toBe(ActionEventType::Delete)
        ->and($row->original['name'])->toBe($tag->name)
        ->and($row->changes)->toBeNull()
        ->and($row->actionable_type)->toBe(Tag::class);
});

it('writes one restore row with neither original nor changes, then one force delete row', function () {
    [$first, $second] = Post::onlyTrashed()->limit(2)->get()->all();

    $this->putJson("/lathe/api/resources/posts/{$first->id}/restore")->assertOk();
    $restored = ActionEvent::sole();
    expect($restored->type)->toBe(ActionEventType::Restore)
        ->and($restored->original)->toBeNull()
        ->and($restored->changes)->toBeNull()
        ->and($restored->resource_class)->toBe(PostResource::class);

    $this->deleteJson("/lathe/api/resources/posts/{$second->id}/force")->assertOk();
    $forced = ActionEvent::query()->where('type', 'force-delete')->sole();
    expect($forced->original['title'])->toBe($second->title)
        ->and(ActionEvent::count())->toBe(2)
        ->and(ActionEvent::distinct()->count('batch_id'))->toBe(2);
});

it('writes one update row for an inline edit of an order status', function () {
    $order = Order::query()->where('status', '!=', 'shipped')->firstOrFail();

    $this->patchJson("/lathe/api/resources/orders/{$order->id}/field/status", ['value' => 'shipped'])->assertOk();

    $row = ActionEvent::sole();

    expect($row->type)->toBe(ActionEventType::Update)
        ->and($row->resource_class)->toBe(OrderResource::class)
        ->and($row->original['status'])->toBe($order->status)
        ->and($row->changes['status'])->toBe('shipped');
});

it('rolls the record and its row back when a later listener throws', function () {
    $name = Customer::query()->findOrFail(1)->name;
    Event::listen(ResourceUpdated::class, fn () => throw new RuntimeException('late listener'));

    $this->putJson('/lathe/api/resources/customers/1', ['name' => 'Renamed', 'email' => 'x@example.test', 'country' => 'US', 'type' => 'person'])->assertStatus(500);

    expect(Customer::query()->findOrFail(1)->name)->toBe($name)
        ->and(DB::table('lathe_action_events')->count())->toBe(0);
});
