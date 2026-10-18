<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Lathe\ActionEvents\ActionEvent;
use Lathe\ActionEvents\ActionEventPolicy;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->admin = User::where('email', 'admin@lathephp.com')->firstOrFail();
    $this->actingAs($this->admin);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function historyEvent(Order $order, string $at, array $attributes = []): ActionEvent
{
    return ActionEvent::query()->forceCreate([
        'batch_id' => (string) Str::uuid(),
        'type' => 'update',
        'status' => 'finished',
        'actionable_type' => $order->getMorphClass(),
        'actionable_id' => (string) $order->getKey(),
        'created_at' => Carbon::parse($at),
        ...$attributes,
    ]);
}

it('lists the newest five events of a record, newest first, with who, what and when', function () {
    $order = Order::query()->findOrFail(12);
    ActionEvent::query()->forActionable($order)->delete();
    historyEvent($order, '2026-10-17 09:00:00', ['type' => 'create']);
    foreach (range(1, 4) as $hour) {
        historyEvent($order, "2026-10-17 1{$hour}:00:00", ['user_id' => $this->admin->id, 'changes' => ['status' => 'paid', 'updated_at' => '2026-10-17 1'.$hour.':00:00']]);
    }
    historyEvent($order, '2026-10-17 20:00:00', ['type' => 'action-ran', 'action_name' => 'Mark Shipped', 'user_id' => $this->admin->id]);
    historyEvent($order, '2026-10-17 21:00:00', ['type' => 'attach', 'user_id' => $this->admin->id]);

    $events = $this->getJson('/lathe/api/resources/orders/12/history')->assertOk()->json('events');

    expect($events)->toHaveCount(5)
        ->and($events[0])->toMatchArray(['type' => 'attach', 'title' => 'Attached', 'user' => $this->admin->name, 'at' => '2026-10-17T21:00:00Z'])
        ->and($events[1])->toMatchArray(['type' => 'action-ran', 'title' => 'Mark Shipped'])
        ->and($events[2])->toMatchArray(['type' => 'update', 'title' => 'Changed Status', 'at' => '2026-10-17T14:00:00Z'])
        ->and(collect($events)->pluck('type')->all())->not->toContain('create');
});

it('says Updated when no changed attribute has a field', function () {
    $order = Order::query()->findOrFail(12);
    ActionEvent::query()->forActionable($order)->delete();
    historyEvent($order, '2026-10-17 09:00:00', ['changes' => ['updated_at' => '2026-10-17 09:00:00']]);

    expect($this->getJson('/lathe/api/resources/orders/12/history')->json('events.0.title'))->toBe('Updated');
});

it('answers an empty list for a record with no events', function () {
    $order = Order::query()->findOrFail(12);
    ActionEvent::query()->forActionable($order)->delete();

    $this->getJson('/lathe/api/resources/orders/12/history')->assertOk()->assertExactJson(['events' => []]);
});

it('answers 403 to a viewer who may not read the action log', function () {
    Gate::policy(ActionEvent::class, ActionEventPolicy::class);

    $this->getJson('/lathe/api/resources/orders/12/history')->assertForbidden();
});

it('answers 404 for a record whose model keeps no action log', function () {
    $this->getJson('/lathe/api/resources/tags/1/history')->assertNotFound();
});
