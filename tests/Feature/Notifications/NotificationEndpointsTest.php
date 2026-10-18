<?php

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Lathe\Notifications\LatheNotification;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    Gate::define('viewLathe', fn () => true);
    $this->user = User::factory()->create();
    $this->other = User::factory()->create();
});

/**
 * Stores $count notifications for the user, the first at $at and each next one second later.
 *
 * @return list<string>
 */
function notifyUser(User $user, int $count = 1, ?string $readAt = null, ?Carbon $at = null): array
{
    $ids = [];

    for ($i = 0; $i < $count; $i++) {
        $row = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => LatheNotification::class,
            'data' => (new LatheNotification)->message("n{$i}")->success()->toArray(),
            'read_at' => $readAt,
        ]);
        $row->forceFill(['created_at' => ($at ?? now())->copy()->addSeconds($i)])->save();
        $ids[] = $row->id;
    }

    return $ids;
}

test('list_puts_a_notification_sent_one_second_later_first_whatever_its_id', function () {
    $at = Carbon::parse('2026-10-19 12:00:00');
    [$older, $newer] = [
        ['id' => 'ffffffff-0000-4000-8000-000000000001', 'at' => $at],
        ['id' => '00000000-0000-4000-8000-000000000002', 'at' => $at->copy()->addSecond()],
    ];

    foreach ([$older, $newer] as $row) {
        $this->user->notifications()->create([
            'id' => $row['id'],
            'type' => LatheNotification::class,
            'data' => (new LatheNotification)->message($row['id'])->toArray(),
        ])->forceFill(['created_at' => $row['at']])->save();
    }

    $ids = collect($this->actingAs($this->user)->getJson('/lathe/api/notifications')->assertOk()->json('notifications'))->pluck('id')->all();

    expect($ids)->toBe([$newer['id'], $older['id']]);
});

test('list_returns_the_newest_100_first_with_the_unread_count', function () {
    notifyUser($this->user, 101);

    $json = $this->actingAs($this->user)->getJson('/lathe/api/notifications')->assertOk()->json();

    expect($json['notifications'])->toHaveCount(100)
        ->and($json['notifications'][0]['message'])->toBe('n100')
        ->and($json['notifications'][99]['message'])->toBe('n1')
        ->and($json['unreadCount'])->toBe(101);
});

test('list_item_has_exactly_the_documented_keys_and_values', function () {
    Carbon::setTestNow('2026-03-01 12:00:00');
    notifyUser($this->user, 1, null, Carbon::parse('2026-03-01 10:00:00'));

    $item = $this->actingAs($this->user)->getJson('/lathe/api/notifications')->json('notifications.0');

    expect(array_keys($item))->toBe(['id', 'type', 'icon', 'message', 'body', 'notificationType', 'actions', 'duration', 'readAt', 'createdAt', 'createdAtHuman'])
        ->and($item['type'])->toBe(LatheNotification::class)
        ->and($item['icon'])->toBe('check-circle')
        ->and($item['notificationType'])->toBe('success')
        ->and($item['body'])->toBeNull()
        ->and($item['actions'])->toBe([])
        ->and($item['duration'])->toBeNull()
        ->and($item['readAt'])->toBeNull()
        ->and($item['createdAt'])->toBe('2026-03-01T10:00:00+00:00')
        ->and($item['createdAtHuman'])->toBe('2 hours ago');

    Carbon::setTestNow();
});

test('count_returns_the_unread_total', function () {
    notifyUser($this->user, 2);
    notifyUser($this->user, 1, now()->toDateTimeString());
    notifyUser($this->other, 4);

    $this->actingAs($this->user)->getJson('/lathe/api/notifications/count')->assertOk()->assertExactJson(['count' => 2]);
});

test('read_unread_and_mark_all_read_answer_204', function () {
    [$one] = notifyUser($this->user, 2);
    $this->actingAs($this->user);

    $this->putJson("/lathe/api/notifications/{$one}")->assertNoContent();
    expect(DatabaseNotification::find($one)->read_at)->not->toBeNull();

    $this->postJson("/lathe/api/notifications/{$one}/unread")->assertNoContent();
    expect(DatabaseNotification::find($one)->read_at)->toBeNull();

    $this->postJson('/lathe/api/notifications/mark-all-read')->assertNoContent();
    expect(DatabaseNotification::whereNull('read_at')->count())->toBe(0);
});

test('delete_one_and_delete_all_answer_204', function () {
    [$one] = notifyUser($this->user, 3);
    $keep = notifyUser($this->other)[0];
    $this->actingAs($this->user);

    $this->deleteJson("/lathe/api/notifications/{$one}")->assertNoContent();
    expect(DatabaseNotification::count())->toBe(3);

    $this->deleteJson('/lathe/api/notifications')->assertNoContent();
    expect(DatabaseNotification::pluck('id')->all())->toBe([$keep]);
});

test('another_users_id_changes_nothing', function () {
    [$theirs] = notifyUser($this->other, 1);
    $this->actingAs($this->user);

    $this->putJson("/lathe/api/notifications/{$theirs}")->assertNoContent();
    $this->deleteJson("/lathe/api/notifications/{$theirs}")->assertNoContent();
    $this->postJson('/lathe/api/notifications/mark-all-read')->assertNoContent();
    $this->deleteJson('/lathe/api/notifications')->assertNoContent();

    expect(DatabaseNotification::find($theirs))->not->toBeNull()
        ->and(DatabaseNotification::find($theirs)->read_at)->toBeNull();
});

test('an_id_that_is_no_uuid_changes_nothing', function () {
    [$mine] = notifyUser($this->user, 1);
    $this->actingAs($this->user);

    $this->putJson('/lathe/api/notifications/1')->assertNoContent();
    $this->postJson('/lathe/api/notifications/1/unread')->assertNoContent();
    $this->deleteJson('/lathe/api/notifications/1')->assertNoContent();

    expect(DatabaseNotification::find($mine))->not->toBeNull()
        ->and(DatabaseNotification::find($mine)->read_at)->toBeNull();
});

test('every_endpoint_answers_401_with_no_user', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/lathe/api/notifications'],
    ['GET', '/lathe/api/notifications/count'],
    ['PUT', '/lathe/api/notifications/abc'],
    ['POST', '/lathe/api/notifications/abc/unread'],
    ['POST', '/lathe/api/notifications/mark-all-read'],
    ['DELETE', '/lathe/api/notifications/abc'],
    ['DELETE', '/lathe/api/notifications'],
]);

test('every_endpoint_answers_404_when_notifications_are_disabled', function (string $method, string $uri) {
    config(['lathe.notifications.enabled' => false]);

    $this->actingAs($this->user)->json($method, $uri)->assertNotFound();
})->with([
    ['GET', '/lathe/api/notifications'],
    ['GET', '/lathe/api/notifications/count'],
    ['PUT', '/lathe/api/notifications/abc'],
    ['POST', '/lathe/api/notifications/abc/unread'],
    ['POST', '/lathe/api/notifications/mark-all-read'],
    ['DELETE', '/lathe/api/notifications/abc'],
    ['DELETE', '/lathe/api/notifications'],
]);

test('doctor_prints_a_fail_when_the_notifications_table_is_missing', function () {
    Schema::drop('notifications');

    Artisan::call('lathe:doctor');

    expect(Artisan::output())->toContain('FAIL Table notifications is missing');
});

test('a_missing_table_gives_an_empty_list_and_count_zero', function () {
    Schema::drop('notifications');
    $this->actingAs($this->user);

    $this->getJson('/lathe/api/notifications')->assertOk()->assertExactJson(['notifications' => [], 'unreadCount' => 0]);
    $this->getJson('/lathe/api/notifications/count')->assertOk()->assertExactJson(['count' => 0]);
});
