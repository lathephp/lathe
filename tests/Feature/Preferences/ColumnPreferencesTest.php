<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lathe\Preferences\UserPreference;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => in_array($user->email, ['admin@lathephp.com', 'editor@lathephp.com'], true));
});

function columnsAs(string $email): User
{
    $user = User::where('email', $email)->firstOrFail();
    test()->actingAs($user);

    return $user;
}

const COLUMNS_URL = '/lathe/api/resources/orders/columns';

it('sends the code columns, no choice and every column to a user with no saved choice', function () {
    columnsAs('admin@lathephp.com');

    $table = $this->getJson('/lathe/api/resources/orders')->assertOk()->json('table');

    expect(array_column($table['columns'], 'key'))->toBe(['number', 'customer', 'status', 'total_cents', 'placed_at'])
        ->and(array_filter($table['columns'], fn (array $column) => array_key_exists('pinned', $column)))->toBe([])
        ->and($table['columnChoice'])->toBeNull()
        ->and($table['allColumns'])->toBe([
            ['key' => 'number', 'label' => 'Number'],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'total_cents', 'label' => 'Total'],
            ['key' => 'placed_at', 'label' => 'Placed'],
        ]);
});

it('hides and pins columns for the user who saved them only', function () {
    columnsAs('admin@lathephp.com');

    $this->putJson(COLUMNS_URL, ['order' => ['number', 'customer', 'status', 'total_cents', 'placed_at'], 'hidden' => ['customer'], 'pinned' => ['total_cents']])
        ->assertOk()
        ->assertExactJson(['order' => ['number', 'customer', 'status', 'total_cents', 'placed_at'], 'hidden' => ['customer'], 'pinned' => ['total_cents']]);

    $admin = $this->getJson('/lathe/api/resources/orders')->assertOk()->json('table');

    expect(array_column($admin['columns'], 'pinned', 'key'))->toBe(['number' => false, 'status' => false, 'total_cents' => true, 'placed_at' => false])
        ->and($admin['columnChoice'])->toBe(['order' => ['number', 'customer', 'status', 'total_cents', 'placed_at'], 'hidden' => ['customer'], 'pinned' => ['total_cents']]);

    columnsAs('editor@lathephp.com');
    $editor = $this->getJson('/lathe/api/resources/orders')->assertOk()->json('table');

    expect(array_column($editor['columns'], 'key'))->toBe(['number', 'customer', 'status', 'total_cents', 'placed_at'])
        ->and($editor['columnChoice'])->toBeNull();
});

it('cleans the saved choice of unknown keys and duplicates', function () {
    columnsAs('admin@lathephp.com');

    $this->putJson(COLUMNS_URL, ['order' => ['number', 'placed_at', 'nope', 'placed_at'], 'hidden' => ['status', 'status'], 'pinned' => ['nope']])
        ->assertOk()
        ->assertExactJson(['order' => ['number', 'placed_at', 'customer', 'status', 'total_cents'], 'hidden' => ['status'], 'pinned' => []]);

    expect(array_column($this->getJson('/lathe/api/resources/orders')->json('table.columns'), 'key'))->toBe(['number', 'placed_at', 'customer', 'total_cents']);
});

it('refuses a malformed choice', function (array $body) {
    columnsAs('admin@lathephp.com');

    $this->putJson(COLUMNS_URL, $body)->assertUnprocessable();

    expect(UserPreference::count())->toBe(0);
})->with([
    'order not a list' => [['order' => 'number', 'hidden' => [], 'pinned' => []]],
    'a hidden item not a string' => [['order' => [], 'hidden' => [7], 'pinned' => []]],
    'pinned missing' => [['order' => [], 'hidden' => []]],
]);

it('resets to the code order', function () {
    columnsAs('admin@lathephp.com');
    $this->putJson(COLUMNS_URL, ['order' => ['placed_at', 'number'], 'hidden' => ['customer'], 'pinned' => ['number']])->assertOk();

    $this->deleteJson(COLUMNS_URL)->assertNoContent();

    $table = $this->getJson('/lathe/api/resources/orders')->assertOk()->json('table');

    expect(array_column($table['columns'], 'key'))->toBe(['number', 'customer', 'status', 'total_cents', 'placed_at'])
        ->and($table['columnChoice'])->toBeNull()
        ->and(UserPreference::count())->toBe(0);
});

it('writes only the signed-in user\'s row', function () {
    $admin = columnsAs('admin@lathephp.com');
    $this->putJson(COLUMNS_URL, ['order' => [], 'hidden' => ['customer'], 'pinned' => []])->assertOk();

    $editor = columnsAs('editor@lathephp.com');
    $this->putJson(COLUMNS_URL, ['order' => [], 'hidden' => ['status'], 'pinned' => ['number']])->assertOk();

    $rows = DB::table('lathe_user_preferences')->orderBy('id')->get(['user_id', 'key']);

    expect($rows->map(fn (object $row) => [$row->user_id, $row->key])->all())->toBe([
        [(string) $admin->getKey(), 'columns:orders'],
        [(string) $editor->getKey(), 'columns:orders'],
    ])
        ->and(keysSorted(UserPreference::where('user_id', (string) $admin->getKey())->sole()->value))->toBe(['hidden' => ['customer'], 'order' => ['number', 'customer', 'status', 'total_cents', 'placed_at'], 'pinned' => []]);

    $this->deleteJson(COLUMNS_URL)->assertNoContent();

    expect(UserPreference::pluck('user_id')->all())->toBe([(string) $admin->getKey()]);
});
