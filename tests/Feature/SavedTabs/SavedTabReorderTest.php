<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
});

afterEach(function () {
    $connection = config('database.default');

    if (config("database.connections.{$connection}.prefix") === 'lt_') {
        Schema::dropAllTables();
        config()->set("database.connections.{$connection}.prefix", '');
        DB::purge($connection);
    }
});

function reorderUrl(string $suffix = ''): string
{
    return "/lathe/api/resources/orders/saved-tabs{$suffix}";
}

function reorderAdmin(): User
{
    $user = User::where('email', 'admin@lathephp.com')->firstOrFail();
    test()->actingAs($user);

    return $user;
}

/**
 * @param  list<string>  $names
 * @return list<int>
 */
function createTabs(array $names): array
{
    return array_map(fn (string $name) => test()->postJson(reorderUrl(), ['name' => $name])->assertCreated()->json('id'), $names);
}

/**
 * @return list<string>
 */
function tabNames(): array
{
    return array_column(test()->getJson(reorderUrl())->assertOk()->json('tabs'), 'name');
}

/**
 * @return list<array{query: string}>
 */
function loggedUpdates(): array
{
    return array_values(array_filter(DB::getQueryLog(), fn ($q) => str_starts_with(strtolower($q['query']), 'update')));
}

it('stores the new order as positions 0 to n-1 and lists tabs in that order', function () {
    reorderAdmin();
    [$paid, $cancelled] = createTabs(['Paid', 'Cancelled']);

    $this->putJson(reorderUrl('/reorder'), ['ids' => [$cancelled, $paid]])->assertNoContent();

    expect(tabNames())->toBe(['Cancelled', 'Paid']);
    expect(DB::table('lathe_saved_tabs')->pluck('position', 'id')->map(fn ($p) => (int) $p)->all())->toBe([$paid => 1, $cancelled => 0]);
});

it('answers 422 and writes nothing when an id belongs to another user', function () {
    reorderAdmin();
    [$paid, $cancelled] = createTabs(['Paid', 'Cancelled']);
    $foreign = DB::table('lathe_saved_tabs')->insertGetId([
        'user_id' => 'someone-else', 'resource_class' => DB::table('lathe_saved_tabs')->value('resource_class'),
        'name' => 'Theirs', 'position' => 7, 'show_count' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->putJson(reorderUrl('/reorder'), ['ids' => [$foreign, $cancelled, $paid]])->assertUnprocessable()->assertJsonValidationErrors('ids');

    expect(tabNames())->toBe(['Paid', 'Cancelled']);
    expect((int) DB::table('lathe_saved_tabs')->where('id', $foreign)->value('position'))->toBe(7);
});

it('answers 422 when an id is missing, duplicated or unknown', function (array $ids) {
    reorderAdmin();
    [$paid, $cancelled] = createTabs(['Paid', 'Cancelled']);
    $payload = array_map(fn ($id) => $id === 'paid' ? $paid : ($id === 'cancelled' ? $cancelled : $id), $ids);

    $this->putJson(reorderUrl('/reorder'), ['ids' => $payload])->assertUnprocessable()->assertJsonValidationErrors('ids');

    expect(tabNames())->toBe(['Paid', 'Cancelled']);
})->with([
    'missing' => [['cancelled']],
    'duplicate' => [['paid', 'paid', 'cancelled']],
    'empty' => [[]],
    'unknown' => [['cancelled', 'paid', 9999]],
]);

it('rewrites every position in a single update statement', function () {
    reorderAdmin();
    [$a, $b, $c] = createTabs(['A', 'B', 'C']);

    DB::enableQueryLog();
    $this->putJson(reorderUrl('/reorder'), ['ids' => [$c, $a, $b]])->assertNoContent();
    $updates = loggedUpdates();

    expect($updates)->toHaveCount(1);
    expect(strtolower($updates[0]['query']))->toContain('case');
    expect(tabNames())->toBe(['C', 'A', 'B']);
});

it('builds the update on the prefixed table name', function () {
    $connection = config('database.default');
    // Drop the unprefixed tables first: PostgreSQL index names are schema-wide and would collide.
    Schema::dropAllTables();
    config()->set("database.connections.{$connection}.prefix", 'lt_');
    DB::purge($connection);
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    reorderAdmin();
    [$paid, $cancelled] = createTabs(['Paid', 'Cancelled']);

    DB::enableQueryLog();
    $this->putJson(reorderUrl('/reorder'), ['ids' => [$cancelled, $paid]])->assertNoContent();
    $updates = loggedUpdates();

    expect($updates)->toHaveCount(1);
    expect($updates[0]['query'])->toContain('lt_lathe_saved_tabs');
    expect(tabNames())->toBe(['Cancelled', 'Paid']);
});
