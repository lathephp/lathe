<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Lathe\Doctor\Doctor;

function migrateLathe(): void
{
    test()->artisan('migrate', [
        '--path' => dirname(__DIR__, 3).'/database/migrations',
        '--realpath' => true,
    ])->assertExitCode(0);
}

beforeEach(function () {
    Schema::dropAllTables();
});

afterEach(function () {
    Schema::dropAllTables();
});

it('creates the ten Lathe tables', function () {
    migrateLathe();

    foreach ([
        'lathe_pending_uploads', 'lathe_action_events', 'lathe_bulk_runs', 'lathe_saved_tabs',
        'notifications', 'lathe_exports', 'lathe_imports', 'lathe_failed_import_rows',
        'lathe_field_attachments', 'lathe_plugin_settings',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("missing table {$table}");
    }
});

it('gives each table the columns its spec names', function (string $table, array $columns) {
    migrateLathe();

    expect(Schema::getColumnListing($table))->toEqualCanonicalizing($columns);
})->with([
    'bulk runs' => ['lathe_bulk_runs', [
        'id', 'user_id', 'resource_class', 'action_name', 'batch_id', 'status', 'total', 'processed',
        'failed', 'skipped', 'cancelled_at', 'completed_at', 'created_at', 'updated_at',
    ]],
    'imports' => ['lathe_imports', [
        'id', 'user_id', 'file_disk', 'file_name', 'original_name', 'model_class', 'column_map', 'status',
        'mode', 'total_rows', 'processed_rows', 'last_processed_row', 'created_rows', 'updated_rows',
        'skipped_rows', 'failed_rows', 'error_file', 'exception', 'completed_at', 'created_at', 'updated_at',
    ]],
    'exports' => ['lathe_exports', [
        'id', 'user_id', 'file_disk', 'file_name', 'total_rows', 'processed_rows', 'successful_rows',
        'completed_at', 'failed_at', 'exception', 'created_at', 'updated_at',
    ]],
    'pending uploads' => ['lathe_pending_uploads', [
        'id', 'user_id', 'field', 'disk', 'path', 'original_name', 'size', 'draft_id', 'status', 'created_at',
    ]],
    'action events' => ['lathe_action_events', [
        'id', 'batch_id', 'user_id', 'type', 'actionable_type', 'actionable_id', 'target_type', 'target_id',
        'action_name', 'resource_class', 'original', 'changes', 'fields', 'status', 'exception',
        'ip_address', 'user_agent', 'created_at', 'updated_at',
    ]],
    'saved tabs' => ['lathe_saved_tabs', [
        'id', 'user_id', 'resource_class', 'name', 'filters', 'q', 'sort', 'trashed', 'show_count',
        'badge_type', 'position', 'schema_hash', 'created_at', 'updated_at',
    ]],
    'failed import rows' => ['lathe_failed_import_rows', [
        'id', 'import_id', 'row_number', 'data', 'errors', 'created_at', 'updated_at',
    ]],
    'field attachments' => ['lathe_field_attachments', [
        'id', 'attachable_type', 'attachable_id', 'attachment', 'disk', 'url', 'created_at', 'updated_at',
    ]],
    'plugin settings' => ['lathe_plugin_settings', [
        'plugin_key', 'data', 'created_at', 'updated_at',
    ]],
]);

it('stores key columns as strings', function (string $table, string $column) {
    migrateLathe();

    $type = collect(Schema::getColumns($table))->firstWhere('name', $column)['type_name'];

    expect($type)->toBeIn(['varchar', 'char', 'bpchar']);
})->with([
    ['lathe_pending_uploads', 'user_id'],
    ['lathe_action_events', 'user_id'],
    ['lathe_action_events', 'actionable_id'],
    ['lathe_action_events', 'target_id'],
    ['lathe_bulk_runs', 'user_id'],
    ['lathe_saved_tabs', 'user_id'],
    ['lathe_exports', 'user_id'],
    ['lathe_imports', 'user_id'],
    ['lathe_field_attachments', 'attachable_id'],
]);

it('keeps the named indexes', function () {
    migrateLathe();

    $names = fn (string $table) => collect(Schema::getIndexes($table))->pluck('name')->all();

    expect($names('lathe_action_events'))->toContain('lathe_ae_target_index', 'lathe_ae_actionable_index')
        ->and($names('lathe_saved_tabs'))->toContain('lathe_saved_tabs_user_resource_idx');
});

// A standalone action runs on no record, so its audit row has none.
it('takes an action event with no record', function () {
    migrateLathe();

    DB::table('lathe_action_events')->insert(['batch_id' => (string) Str::uuid(), 'type' => 'action-ran', 'action_name' => 'Send Report']);

    expect(DB::table('lathe_action_events')->whereNull('actionable_id')->whereNull('target_id')->count())->toBe(1);
});

it('leaves an existing notifications table untouched', function () {
    Schema::create('notifications', function ($table) {
        $table->id();
        $table->string('marker');
    });

    migrateLathe();

    expect(Schema::getColumnListing('notifications'))->toBe(['id', 'marker']);
});

it('works with an integer, UUID, ULID or string user key', function (string $keyType, Closure $makeKey) {
    Schema::create('users', function ($table) use ($keyType) {
        match ($keyType) {
            'int' => $table->id(),
            'uuid' => $table->uuid('id')->primary(),
            'ulid' => $table->ulid('id')->primary(),
            'string' => $table->string('id')->primary(),
        };
        $table->string('name');
    });

    DB::table('users')->insert(array_filter(['id' => $keyType === 'int' ? null : $makeKey(), 'name' => 'Ada']));
    $userId = (string) DB::table('users')->value('id');

    migrateLathe();

    $now = now();
    $rows = [
        'lathe_pending_uploads' => ['id' => (string) Str::ulid(), 'field' => 'avatar', 'disk' => 'local', 'path' => 'a', 'original_name' => 'a.png'],
        'lathe_action_events' => ['batch_id' => (string) Str::uuid(), 'type' => 'update', 'actionable_type' => 'post', 'actionable_id' => $userId, 'target_type' => 'post', 'target_id' => $userId, 'created_at' => $now, 'updated_at' => $now],
        'lathe_bulk_runs' => ['id' => (string) Str::ulid(), 'resource_class' => 'App\\Lathe\\Posts', 'action_name' => 'publish', 'created_at' => $now, 'updated_at' => $now],
        'lathe_saved_tabs' => ['resource_class' => 'App\\Lathe\\Posts', 'name' => 'Mine', 'created_at' => $now, 'updated_at' => $now],
        'lathe_exports' => ['created_at' => $now, 'updated_at' => $now],
        'lathe_imports' => ['file_name' => 'a.csv', 'model_class' => 'App\\Models\\Post', 'created_at' => $now, 'updated_at' => $now],
    ];

    foreach ($rows as $table => $row) {
        DB::table($table)->insert($row + ['user_id' => $userId]);

        expect((string) DB::table($table)->value('user_id'))->toBe($userId, $table);
    }

    DB::table('lathe_field_attachments')->insert(['attachable_type' => 'users', 'attachable_id' => $userId, 'attachment' => 'a', 'disk' => 'local', 'url' => '/a']);

    expect((string) DB::table('lathe_field_attachments')->value('attachable_id'))->toBe($userId);
})->with([
    'int key' => ['int', fn () => 7],
    'uuid key' => ['uuid', fn () => (string) Str::uuid()],
    'ulid key' => ['ulid', fn () => (string) Str::ulid()],
    'string key' => ['string', fn () => 'ada-lovelace'],
]);

it('has lathe:doctor check every table the migrations create', function () {
    $created = [];

    foreach (glob(dirname(__DIR__, 3).'/database/migrations/*.php') ?: [] as $migration) {
        preg_match_all("/Schema::create\\('([^']+)'/", (string) file_get_contents($migration), $tables);
        array_push($created, ...$tables[1]);
    }

    expect($created)->not->toBeEmpty()
        ->and(Doctor::TABLES)->toEqualCanonicalizing($created);
});
