<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lathe\ActionEvents\ActionEvent;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
});

/**
 * @return array<string, mixed>
 */
function pruneRow(string $record, int $daysAgo): array
{
    return [
        'batch_id' => (string) Str::uuid(),
        'type' => 'update',
        'status' => 'finished',
        'actionable_type' => 'Workbench\\App\\Models\\Order',
        'actionable_id' => $record,
        'created_at' => now()->subDays($daysAgo),
        'updated_at' => now()->subDays($daysAgo),
    ];
}

/**
 * @return list<int>
 */
function pruneRemaining(): array
{
    return ActionEvent::query()->orderBy('id')->pluck('id')->map(fn (mixed $id) => (int) $id)->all();
}

it('deletes the events older than --days', function () {
    DB::table('lathe_action_events')->insert([pruneRow('1', 40), pruneRow('1', 31), pruneRow('1', 10), pruneRow('2', 1)]);

    $this->artisan('lathe:prune-action-events', ['--days' => 30])->assertSuccessful();

    expect(pruneRemaining())->toBe([3, 4]);
});

it('keeps the newest --keep events of each record', function () {
    DB::table('lathe_action_events')->insert([
        pruneRow('1', 5), pruneRow('1', 4), pruneRow('1', 3), pruneRow('1', 2), pruneRow('1', 1),
        pruneRow('2', 3), pruneRow('2', 2),
        pruneRow('3', 1),
    ]);

    $this->artisan('lathe:prune-action-events', ['--keep' => 2])->assertSuccessful();

    expect(pruneRemaining())->toBe([4, 5, 6, 7, 8]);
});

it('breaks a tie of timestamps by the higher id', function () {
    $same = pruneRow('1', 2);
    DB::table('lathe_action_events')->insert([$same, $same, $same]);

    $this->artisan('lathe:prune-action-events', ['--keep' => 1])->assertSuccessful();

    expect(pruneRemaining())->toBe([3]);
});

it('takes its defaults from the action_events config', function () {
    config(['lathe.action_events.prune_after_days' => 5, 'lathe.action_events.prune_per_actionable' => 1]);
    DB::table('lathe_action_events')->insert([pruneRow('1', 9), pruneRow('1', 3), pruneRow('1', 2), pruneRow('2', 1)]);

    $this->artisan('lathe:prune-action-events')->assertSuccessful();

    expect(pruneRemaining())->toBe([3, 4]);
});

it('prunes with the shipped config by age only', function () {
    DB::table('lathe_action_events')->insert([pruneRow('1', 31), pruneRow('1', 3), pruneRow('1', 2)]);

    $this->artisan('lathe:prune-action-events')->assertSuccessful();

    expect(pruneRemaining())->toBe([2, 3]);
});

it('leaves the same rows as model:prune', function () {
    DB::table('lathe_action_events')->insert([pruneRow('1', 40), pruneRow('1', 31), pruneRow('1', 10), pruneRow('2', 1)]);

    $this->artisan('model:prune', ['--model' => [ActionEvent::class]])->assertSuccessful();

    expect(pruneRemaining())->toBe([3, 4]);
});

it('deletes in chunks of 1,000 ids picked by a ranked subquery', function () {
    foreach (array_chunk(array_map(fn () => pruneRow('1', 1), range(1, 2500)), 500) as $chunk) {
        DB::table('lathe_action_events')->insert($chunk);
    }
    $queries = [];
    DB::listen(function ($event) use (&$queries) {
        $queries[] = strtolower($event->sql);
    });

    $this->artisan('lathe:prune-action-events', ['--keep' => 1])->assertSuccessful();

    $deletes = array_filter($queries, fn (string $sql) => str_starts_with($sql, 'delete'));
    $ranked = array_filter($queries, fn (string $sql) => str_contains($sql, 'row_number() over (partition by'));

    expect(pruneRemaining())->toBe([2500])
        ->and($deletes)->toHaveCount(3)
        ->and($ranked)->not->toBeEmpty();
});
