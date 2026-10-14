<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lathe\Actions\BulkRun;
use Lathe\Actions\Export\Export;
use Lathe\Actions\Import\Import;
use Lathe\Uploads\PendingUpload;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    Storage::fake('local');
    config(['lathe.exports.prune_after_hours' => 24, 'lathe.imports.prune_after_hours' => 24, 'lathe.action_events.prune_after_days' => 30, 'lathe.uploads.pending_hours' => 24]);
});

/**
 * Creates one row of each kind, $hours old.
 *
 * @return array<string, int|string>
 */
function rowsAged(int $hours): array
{
    test()->travel(-$hours)->hours();

    $ids = [
        'export' => Export::forceCreate(['user_id' => '1', 'file_disk' => 'local', 'file_name' => "export-{$hours}.csv"])->getKey(),
        'import' => Import::forceCreate(['user_id' => '1', 'file_disk' => 'local', 'file_name' => "import-{$hours}.csv", 'original_name' => 'p.csv', 'model_class' => 'X', 'column_map' => [], 'status' => 'finished', 'mode' => 'import'])->getKey(),
        'upload' => PendingUpload::forceCreate(['user_id' => '1', 'field' => 'image', 'disk' => 'local', 'path' => "u-{$hours}", 'original_name' => 'a.png', 'size' => 1, 'draft_id' => (string) Str::uuid(), 'status' => 'pending'])->getKey(),
    ];

    test()->travelBack();

    return $ids;
}

function bulkRun(int $days, string $status): string
{
    test()->travel(-$days)->days();
    $id = BulkRun::forceCreate(['user_id' => '1', 'resource_class' => 'X', 'action_name' => 'mark-shipped', 'status' => $status, 'total' => 1])->getKey();
    test()->travelBack();

    return $id;
}

it('deletes each kind older than its limit and keeps the newer one, printing five counts', function () {
    $old = rowsAged(25);
    $new = rowsAged(23);
    $oldRun = bulkRun(31, 'finished');
    $newRun = bulkRun(29, 'finished');
    $running = bulkRun(31, 'running');
    DB::table('lathe_action_events')->insert(['batch_id' => (string) Str::uuid(), 'type' => 'update', 'status' => 'finished', 'created_at' => now()->subDays(31), 'updated_at' => now()->subDays(31)]);

    expect(Artisan::call('lathe:prune'))->toBe(0);
    $lines = array_values(array_filter(array_map('trim', explode("\n", Artisan::output()))));

    expect($lines)->toHaveCount(5)
        ->and($lines[0])->toContain('1 action events deleted.')
        ->and($lines[1])->toContain('1 exports deleted.')
        ->and($lines[2])->toContain('1 imports deleted.')
        ->and($lines[3])->toContain('1 draft uploads deleted.')
        ->and($lines[4])->toContain('1 bulk runs deleted.');

    expect(Export::pluck('id')->all())->toBe([$new['export']])
        ->and(Import::pluck('id')->all())->toBe([$new['import']])
        ->and(PendingUpload::pluck('id')->all())->toBe([$new['upload']])
        ->and(BulkRun::pluck('id')->sort()->values()->all())->toBe(collect([$newRun, $running])->sort()->values()->all())
        ->and($old['export'])->not->toBe($new['export'])
        ->and($oldRun)->not->toBe($newRun);
});

it('gives model:prune the same result', function () {
    rowsAged(25);
    $new = rowsAged(23);

    $this->artisan('model:prune', ['--model' => [Export::class, Import::class, PendingUpload::class]])->assertSuccessful();

    expect(Export::pluck('id')->all())->toBe([$new['export']])
        ->and(Import::pluck('id')->all())->toBe([$new['import']])
        ->and(PendingUpload::pluck('id')->all())->toBe([$new['upload']]);
});

it('is scheduled daily, and the doctor warns when it is not', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_ends_with((string) $event->command, 'lathe:prune'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 0 * * *');

    Artisan::call('lathe:doctor');
    expect(Artisan::output())->toContain('lathe:prune is scheduled');

    app()->instance(Schedule::class, new Schedule);
    Artisan::call('lathe:doctor');
    expect(Artisan::output())->toMatch('/WARN +lathe:prune is not scheduled/');
});
