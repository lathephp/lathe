<?php

namespace Lathe\Tests\Feature\Workbench;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Lathe\Enums\ActionEventStatus;
use Lathe\Enums\ActionEventType;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

/** Lets the panel list orders but never view one. */
class TimelineDenyingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        return false;
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->admin = User::where('email', 'admin@lathephp.com')->firstOrFail();
    $this->actingAs($this->admin);
});

function logOrderEvent(int $orderId, ActionEventType $type, string $at, array $attributes = []): void
{
    $event = Order::findOrFail($orderId)->actions()->create([
        'batch_id' => (string) Str::uuid(),
        'type' => $type,
        'status' => ActionEventStatus::Finished,
        'user_id' => (string) test()->admin->getKey(),
        ...$attributes,
    ]);
    DB::table('lathe_action_events')->where('id', $event->getKey())->update(['created_at' => $at, 'updated_at' => $at]);
}

it('places the Activity timeline block on the order detail payload', function () {
    $this->get('/lathe/resources/orders/12')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('record.resourcePlugins', [['component' => 'activity-timeline', 'name' => 'Activity Timeline', 'heading' => 'Activity timeline']]));
});

it('lists an order\'s events newest first', function () {
    DB::table('orders')->where('id', 12)->update(['created_at' => '2026-01-02 03:04:05']);
    logOrderEvent(12, ActionEventType::Update, '2026-01-03 10:00:00', ['changes' => ['status' => 'shipped', 'total_cents' => 500]]);
    logOrderEvent(12, ActionEventType::ActionRan, '2026-01-04 11:30:00', ['action_name' => 'Mark shipped']);
    logOrderEvent(13, ActionEventType::Update, '2026-01-05 09:00:00', ['changes' => ['status' => 'paid']]);

    $this->getJson('/lathe/api/resource-plugins/activity-timeline/events?resource=orders&resourceId=12')
        ->assertOk()
        ->assertExactJson(['events' => [
            ['type' => 'action', 'title' => 'Mark shipped', 'detail' => null, 'user' => $this->admin->name, 'at' => '2026-01-04T11:30:00Z'],
            ['type' => 'updated', 'title' => 'Updated', 'detail' => 'status, total_cents', 'user' => $this->admin->name, 'at' => '2026-01-03T10:00:00Z'],
            ['type' => 'created', 'title' => 'Created', 'detail' => null, 'user' => null, 'at' => '2026-01-02T03:04:05Z'],
        ]]);
});

it('keeps the last ten events', function () {
    foreach (range(1, 12) as $day) {
        logOrderEvent(12, ActionEventType::Update, sprintf('2026-02-%02d 10:00:00', $day), ['changes' => ['status' => 'x']]);
    }

    $events = $this->getJson('/lathe/api/resource-plugins/activity-timeline/events?resource=orders&resourceId=12')->assertOk()->json('events');

    expect($events)->toHaveCount(10)
        ->and($events[0]['at'])->toBe('2026-02-12T10:00:00Z')
        ->and($events[9]['at'])->toBe('2026-02-03T10:00:00Z');
});

it('answers for the route record whatever id the query string names', function () {
    logOrderEvent(12, ActionEventType::ActionRan, '2026-01-04 11:30:00', ['action_name' => 'Mark shipped']);
    logOrderEvent(13, ActionEventType::ActionRan, '2026-01-06 11:30:00', ['action_name' => 'Cancel order']);

    $titles = $this->getJson('/lathe/api/resource-plugins/activity-timeline/events?resource=orders&resourceId=12&id=13')->assertOk()->json('events.*.title');

    expect($titles)->toBe(['Mark shipped', 'Created']);
});

it('answers 404 for a missing order and 403 for one the user may not view', function () {
    $this->getJson('/lathe/api/resource-plugins/activity-timeline/events?resource=orders&resourceId=99999999')->assertNotFound();

    Gate::policy(Order::class, TimelineDenyingPolicy::class);

    $this->getJson('/lathe/api/resource-plugins/activity-timeline/events?resource=orders&resourceId=12')->assertForbidden();
});
