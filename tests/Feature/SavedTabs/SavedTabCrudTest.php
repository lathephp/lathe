<?php

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class SavedTabUuidUser extends User
{
    use HasUuids;

    protected $table = 'saved_tab_uuid_users';
}

class SavedTabUlidUser extends User
{
    use HasUlids;

    protected $table = 'saved_tab_ulid_users';
}

class SavedTabStringUser extends User
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'saved_tab_string_users';
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
});

function savedTabsUrl(string $suffix = '', string $resource = 'orders'): string
{
    return "/lathe/api/resources/{$resource}/saved-tabs{$suffix}";
}

function savedTabAdmin(): User
{
    $user = User::where('email', 'admin@lathephp.com')->firstOrFail();
    test()->actingAs($user);

    return $user;
}

it('creates, lists, renames and deletes a saved tab', function () {
    savedTabAdmin();

    $created = $this->postJson(savedTabsUrl(), ['name' => 'Paid', 'filters' => ['status' => 'paid']])
        ->assertCreated()
        ->json();

    expect($created)->toMatchArray([
        'name' => 'Paid',
        'filters' => ['status' => 'paid'],
        'q' => null,
        'sort' => null,
        'trashed' => null,
        'showCount' => false,
        'badgeType' => null,
        'position' => 1,
        'stale' => false,
    ]);

    $this->getJson(savedTabsUrl())->assertOk()->assertJsonCount(1, 'tabs')->assertJsonPath('tabs.0.name', 'Paid');

    $this->putJson(savedTabsUrl('/'.$created['id']), ['name' => 'Settled', 'filters' => ['status' => 'paid'], 'badge_type' => 'success'])
        ->assertOk()
        ->assertJsonPath('name', 'Settled')
        ->assertJsonPath('badgeType', 'success');

    $this->getJson(savedTabsUrl())->assertJsonPath('tabs.0.name', 'Settled');

    $this->deleteJson(savedTabsUrl('/'.$created['id']))->assertNoContent();
    $this->getJson(savedTabsUrl())->assertJsonCount(0, 'tabs');
});

it('stores a schema hash and the user id as a string', function () {
    $admin = savedTabAdmin();

    $this->postJson(savedTabsUrl(), ['name' => 'Paid'])->assertCreated();

    $row = DB::table('lathe_saved_tabs')->first();
    expect($row->user_id)->toBe((string) $admin->getKey())
        ->and($row->resource_class)->toBe('Workbench\App\Lathe\Resources\Order')
        ->and($row->schema_hash)->toMatch('/^[0-9a-f]{40}$/');
});

it('refuses bad input with 422', function (array $payload, string $field) {
    savedTabAdmin();

    $this->postJson(savedTabsUrl(), $payload + ['name' => 'Tab'])->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'long name' => [['name' => str_repeat('a', 101)], 'name'],
    'long q' => [['q' => str_repeat('a', 501)], 'q'],
    'bad badge' => [['badge_type' => 'info'], 'badge_type'],
    'bad trashed' => [['trashed' => 'all'], 'trashed'],
    'unknown filter' => [['filters' => ['nope' => 'x']], 'filter'],
    'bad sort direction' => [['sort' => '-total_cents:up'], 'sort'],
    'unknown sort key' => [['sort' => 'password'], 'sort'],
]);

it('refuses the eleventh tab with the limit in the message', function () {
    savedTabAdmin();

    foreach (range(1, 10) as $i) {
        $this->postJson(savedTabsUrl(), ['name' => "Tab {$i}"])->assertCreated();
    }

    $this->postJson(savedTabsUrl(), ['name' => 'Tab 11'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Maximum of 10 saved tabs per resource');
});

it('uses the configured limit in the message', function () {
    savedTabAdmin();
    config()->set('lathe.saved_tabs.max_per_resource', 2);

    $this->postJson(savedTabsUrl(), ['name' => 'One'])->assertCreated();
    $this->postJson(savedTabsUrl(), ['name' => 'Two'])->assertCreated();
    $this->postJson(savedTabsUrl(), ['name' => 'Three'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Maximum of 2 saved tabs per resource');
});

it('keeps tabs private to their user', function () {
    $admin = savedTabAdmin();
    $id = $this->postJson(savedTabsUrl(), ['name' => 'Mine'])->json('id');

    $this->actingAs(User::where('email', 'editor@lathephp.com')->firstOrFail());

    $this->getJson(savedTabsUrl())->assertJsonCount(0, 'tabs');
    $this->putJson(savedTabsUrl("/{$id}"), ['name' => 'Taken'])->assertNotFound();
    $this->deleteJson(savedTabsUrl("/{$id}"))->assertNotFound();

    expect(DB::table('lathe_saved_tabs')->where('user_id', (string) $admin->getKey())->value('name'))->toBe('Mine');
});

it('answers 404 before any query for a saved tab id that is not a key', function (string $method, string $id) {
    savedTabAdmin();
    $queries = [];
    DB::listen(function (QueryExecuted $event) use (&$queries) {
        $queries[] = $event->sql;
    });

    $this->json($method, savedTabsUrl("/{$id}"), ['name' => 'Two'])->assertNotFound();

    expect(array_filter($queries, fn (string $sql) => str_contains($sql, 'lathe_saved_tabs')))->toBe([]);
})->with([
    'update a word' => ['PUT', 'abc'],
    'update past PHP_INT_MAX' => ['PUT', '99999999999999999999'],
    'delete a word' => ['DELETE', 'abc'],
    'delete past PHP_INT_MAX' => ['DELETE', '99999999999999999999'],
]);

it('answers 403 on every endpoint when saved tabs are off in config', function () {
    savedTabAdmin();
    $id = $this->postJson(savedTabsUrl(), ['name' => 'Mine'])->json('id');
    config()->set('lathe.saved_tabs.enabled', false);

    $this->getJson(savedTabsUrl())->assertForbidden();
    $this->postJson(savedTabsUrl(), ['name' => 'Two'])->assertForbidden();
    $this->putJson(savedTabsUrl("/{$id}"), ['name' => 'Two'])->assertForbidden();
    $this->deleteJson(savedTabsUrl("/{$id}"))->assertForbidden();
});

it('answers 403 for a Resource that does not use saved tabs', function () {
    savedTabAdmin();

    $this->getJson(savedTabsUrl('', 'customers'))->assertForbidden();
    $this->postJson(savedTabsUrl('', 'customers'), ['name' => 'Two'])->assertForbidden();
    $this->putJson(savedTabsUrl('/1', 'customers'), ['name' => 'Two'])->assertForbidden();
    $this->deleteJson(savedTabsUrl('/1', 'customers'))->assertForbidden();
});

it('works for uuid, ulid and string user keys', function (string $class, Closure $idColumn) {
    Schema::create((new $class)->getTable(), function (Blueprint $table) use ($idColumn) {
        $idColumn($table);
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->rememberToken();
        $table->timestamps();
    });

    $user = new $class(['name' => 'Key', 'email' => 'key@example.com', 'password' => 'x']);

    if ($class === SavedTabStringUser::class) {
        $user->id = 'user-key-9';
    }

    $user->save();
    $this->actingAs($user);

    $id = $this->postJson(savedTabsUrl(), ['name' => 'Mine'])->assertCreated()->json('id');
    $this->getJson(savedTabsUrl())->assertJsonCount(1, 'tabs');
    $this->putJson(savedTabsUrl("/{$id}"), ['name' => 'Renamed'])->assertOk();
    expect(DB::table('lathe_saved_tabs')->value('user_id'))->toBe((string) $user->getKey());
    $this->deleteJson(savedTabsUrl("/{$id}"))->assertNoContent();

    expect(DB::table('lathe_saved_tabs')->count())->toBe(0);
})->with([
    'uuid' => [SavedTabUuidUser::class, fn (Blueprint $t) => $t->uuid('id')->primary()],
    'ulid' => [SavedTabUlidUser::class, fn (Blueprint $t) => $t->ulid('id')->primary()],
    'string' => [SavedTabStringUser::class, fn (Blueprint $t) => $t->string('id')->primary()],
]);
