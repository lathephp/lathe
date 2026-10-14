<?php

namespace Lathe\Tests\Feature\Export;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Lathe\Actions\ActionFields;
use Lathe\Actions\Export\Export;
use Lathe\Actions\Export\ExportAction;
use Lathe\Actions\Export\ProcessExport;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\ResourceDefinition;
use OpenSpout\Reader\XLSX\Reader;
use Workbench\App\Lathe\Resources\Order as OrderResource;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;
use ZipArchive;

class ExportDeclared
{
    /** @var list<ExportAction> */
    public static array $actions = [];
}

/**
 * Orders whose index leaves out cancelled rows, and every row when nobody is signed in.
 */
class ScopedExportOrders extends OrderResource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canonical(false);
    }

    public static function indexQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query->where('status', '!=', 'cancelled')->when($request->user() === null, fn (Builder $query) => $query->whereRaw('1 = 0'));
    }
}

class DeclaredExportOrders extends ScopedExportOrders
{
    public function actions(LatheRequest $request): array
    {
        return ExportDeclared::$actions;
    }
}

class NoExportPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        return true;
    }
}

class GuardedExportOrders extends OrderResource
{
    public static ?string $policy = NoExportPolicy::class;

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canonical(false);
    }
}

/**
 * @param  array<string, mixed>  $selection
 * @param  array<string, mixed>  $fields
 * @return array<string, mixed>
 */
function exportBody(array $selection, array $fields = ['filename' => 'orders']): array
{
    return ['action' => 'export', 'selection' => $selection, 'fields' => $fields];
}

/**
 * The rows of a CSV file, without its byte order mark.
 *
 * @return list<list<string|null>>
 */
function csvRows(string $content): array
{
    $content = str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;

    return array_map(fn (string $line) => str_getcsv($line, ',', '"', ''), explode("\n", trim($content)));
}

/**
 * Downloads the file a run's result points at.
 *
 * @return list<list<string|null>>
 */
function downloadedRows(mixed $test, string $url): array
{
    return csvRows((string) $test->get($url)->assertOk()->streamedContent());
}

/**
 * Runs the export job the last run pushed, as a worker would.
 */
function runPushedExport(): ProcessExport
{
    $job = Queue::pushed(ProcessExport::class)->last();
    expect($job)->toBeInstanceOf(ProcessExport::class);
    app()->call([$job, 'handle']);

    return $job;
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    app(DemoSeeder::class)->forOrders(600)->__invoke();
    Gate::define('viewLathe', fn () => true);
    Storage::fake('local');
    ExportDeclared::$actions = [];
    Lathe::registerResources(ScopedExportOrders::class, DeclaredExportOrders::class, GuardedExportOrders::class);
    Order::query()->update(['status' => 'pending']);
    Order::whereKey(1)->update(['number' => 'ORD-1', 'status' => 'paid', 'total_cents' => 123450, 'placed_at' => '2026-03-08 23:30:00']);
    $this->admin = User::where('email', 'admin@lathephp.com')->firstOrFail();
    $this->actingAs($this->admin);
});

it('is an ungrouped Export action, never standalone, recording no action events, with a Filename and a Type', function () {
    $action = ExportAction::make();
    $json = $this->getJson('/lathe/api/resources/orders/actions/export/form')->assertOk()->json();

    expect($action->uriKey())->toBe('export')
        ->and($action->isStandalone())->toBeFalse()
        ->and($action->recordsActionEvents())->toBeFalse()
        ->and($json['action'])->toMatchArray(['uriKey' => 'export', 'name' => 'Export', 'standalone' => false, 'group' => null, 'withoutConfirmation' => false])
        ->and(array_column($json['fields'], 'attribute'))->toBe(['filename', 'type'])
        ->and(array_column($json['fields'], 'label'))->toBe(['Filename', 'Type'])
        ->and($json['fields'][1]['value'])->toBe('csv');
});

it('answers 422 for a selection with no rows', function () {
    $this->postJson('/lathe/api/resources/orders/action', exportBody(['mode' => 'ids', 'ids' => [999999]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['selection' => 'Select at least one row.']);
});

it('answers 403 when the policy has no export method', function () {
    $this->postJson('/lathe/api/resources/guarded-export-orders/action', exportBody(['mode' => 'ids', 'ids' => [1]]))
        ->assertForbidden();

    expect(Export::count())->toBe(0);
});

it('requires a Filename of 1 to 255 characters and a Type of csv or xlsx', function () {
    $url = '/lathe/api/resources/orders/action';
    $ids = ['mode' => 'ids', 'ids' => [1]];

    $this->postJson($url, exportBody($ids, []))->assertUnprocessable()->assertJsonValidationErrors(['filename']);
    $this->postJson($url, exportBody($ids, ['filename' => str_repeat('a', 256)]))->assertUnprocessable()->assertJsonValidationErrors(['filename']);
    $this->postJson($url, exportBody($ids, ['filename' => 'orders', 'type' => 'pdf']))->assertUnprocessable()->assertJsonValidationErrors(['type']);
    $this->postJson($url, exportBody($ids, ['filename' => str_repeat('a', 255), 'type' => 'xlsx']))->assertOk();
});

it('default_export_downloads_in_the_browser', function () {
    $customer = Order::findOrFail(1)->customer->name;

    $response = $this->postJson('/lathe/api/resources/orders/action', exportBody(['mode' => 'ids', 'ids' => [1, 2, 3]], ['filename' => 'orders', 'type' => 'csv']))
        ->assertOk()
        ->assertJsonPath('result.effect.type', 'download')
        ->assertJsonPath('result.effect.name', 'orders.csv')
        ->assertJsonPath('permitted', 3)
        ->assertJsonPath('skipped', 0)
        ->assertJsonPath('failed', 0)
        ->assertJsonPath('errors', [])
        ->assertJsonMissingPath('export_run');

    $rows = downloadedRows($this, $response->json('result.effect.url'));

    expect($rows)->toHaveCount(4)
        ->and($rows[0])->toBe(['Number', 'Customer', 'Status', 'Total', 'Placed'])
        ->and($rows[1])->toBe(['ORD-1', $customer, 'paid', '1234.50', '2026-03-08 23:30:00'])
        ->and(Lathe::actionEventModel()::count())->toBe(0);
});

it('writes a file of 500 rows in the request', function () {
    Queue::fake();

    $response = $this->postJson('/lathe/api/resources/orders/action', exportBody(['mode' => 'ids', 'ids' => range(1, 500)]))
        ->assertOk()
        ->assertJsonPath('result.effect.type', 'download')
        ->assertJsonPath('permitted', 500);

    Queue::assertNothingPushed();
    expect(downloadedRows($this, $response->json('result.effect.url')))->toHaveCount(501);
});

it('queues a file of 501 rows', function () {
    Queue::fake();

    $response = $this->postJson('/lathe/api/resources/orders/action', exportBody(['mode' => 'ids', 'ids' => range(1, 501)]))
        ->assertOk()
        ->assertJsonPath('result', ['message' => 'Export started.', 'level' => 'success', 'effect' => null]);

    $export = Export::firstOrFail();

    Queue::assertPushed(ProcessExport::class, 1);
    expect($response->json('export_run'))->toBe(['id' => $export->id])
        ->and($export->user_id)->toBe((string) $this->admin->id)
        ->and($export->completed_at)->toBeNull();
});

it('export_applies_all_filters', function () {
    Order::whereKey([2, 5, 7])->update(['status' => 'paid']);

    $response = $this->postJson('/lathe/api/resources/orders/action', exportBody(['mode' => 'all', 'filters' => ['status' => 'paid'], 'exclusions' => [5]]))
        ->assertOk();

    expect(array_column(array_slice(downloadedRows($this, $response->json('result.effect.url')), 1), 0))
        ->toBe(['ORD-1', Order::findOrFail(2)->number, Order::findOrFail(7)->number]);
})->group('regression');

it('leaves out rows outside the index query', function () {
    Order::whereKey(2)->update(['status' => 'cancelled']);

    $response = $this->postJson('/lathe/api/resources/scoped-export-orders/action', exportBody(['mode' => 'ids', 'ids' => [1, 2, 3]]))
        ->assertOk()
        ->assertJsonPath('permitted', 2);

    expect(array_column(array_slice(downloadedRows($this, $response->json('result.effect.url')), 1), 0))
        ->toBe(['ORD-1', Order::findOrFail(3)->number]);
});

it('queued_export_applies_resource_scope', function () {
    Queue::fake();
    Order::whereKey(range(2, 11))->update(['status' => 'cancelled']);

    $this->postJson('/lathe/api/resources/scoped-export-orders/action', exportBody(['mode' => 'all']))->assertOk();
    // A worker has nobody signed in until the job restores the actor.
    auth()->forgetUser();
    runPushedExport();

    $export = Export::firstOrFail();
    $rows = csvRows((string) Storage::disk('local')->get((string) $export->file_name));

    expect($export->total_rows)->toBe(590)
        ->and($rows)->toHaveCount(591)
        ->and(array_intersect(array_column($rows, 2), ['cancelled']))->toBe([]);
})->group('regression');

it('reads the rows in key order, exports.chunk_size at a time', function () {
    Queue::fake();
    config(['lathe.exports.chunk_size' => 100]);
    $this->postJson('/lathe/api/resources/orders/action', exportBody(['mode' => 'ids', 'ids' => array_reverse(range(1, 501))]))->assertOk();
    $selects = [];
    DB::listen(function ($query) use (&$selects) {
        if (str_contains($query->sql, 'from "orders"') && str_contains($query->sql, 'limit 100')) {
            $selects[] = $query->sql;
        }
    });

    runPushedExport();
    $rows = csvRows((string) Storage::disk('local')->get((string) Export::firstOrFail()->file_name));

    expect($selects)->toHaveCount(6)
        ->and($rows[1][0])->toBe('ORD-1')
        ->and($rows[501][0])->toBe(Order::findOrFail(501)->number);
});

it('prints a date in the actor timezone', function () {
    Lathe::userTimezone(fn () => 'Pacific/Kiritimati');

    $response = $this->postJson('/lathe/api/resources/orders/action', exportBody(['mode' => 'ids', 'ids' => [1]]))->assertOk();

    expect(downloadedRows($this, $response->json('result.effect.url'))[1][4])->toBe('2026-03-09 13:30:00');
});

it('runs withQuery last, with the modal fields withFields adds', function () {
    Order::whereKey(3)->update(['status' => 'shipped']);
    ExportDeclared::$actions = [ExportAction::make()
        ->withFields(fn (LatheRequest $request) => [Field::text('Status')->required()])
        ->withQuery(fn (Builder $query, ActionFields $fields) => $query->where('status', $fields->get('status')))];
    $url = '/lathe/api/resources/declared-export-orders/action';
    $ids = ['mode' => 'ids', 'ids' => [1, 2, 3]];

    $this->postJson($url, exportBody($ids))->assertUnprocessable()->assertJsonValidationErrors(['status']);
    $response = $this->postJson($url, exportBody($ids, ['filename' => 'orders', 'status' => 'shipped']))->assertOk();

    expect(array_column(downloadedRows($this, $response->json('result.effect.url')), 0))->toBe(['Number', Order::findOrFail(3)->number]);
});

it('maps each row through withFormat', function () {
    Lathe::userTimezone(fn () => 'Pacific/Kiritimati');
    ExportDeclared::$actions = [ExportAction::make()->withFormat(fn (Order $order) => ['Ref' => $order->number, 'Placed' => $order->placed_at, 'Cents' => $order->total_cents])];

    $response = $this->postJson('/lathe/api/resources/declared-export-orders/action', exportBody(['mode' => 'ids', 'ids' => [1]]))->assertOk();

    expect(downloadedRows($this, $response->json('result.effect.url')))->toBe([['Ref', 'Placed', 'Cents'], ['ORD-1', '2026-03-09 13:30:00', '123450']]);
});

it('prefills the Filename and the Type from nameable and withTypeSelector', function () {
    ExportDeclared::$actions = [ExportAction::make()->nameable(fn () => 'daily')->withTypeSelector('xlsx')];

    $json = $this->getJson('/lathe/api/resources/declared-export-orders/actions/export/form')->assertOk()->json();
    $response = $this->postJson('/lathe/api/resources/declared-export-orders/action', exportBody(['mode' => 'ids', 'ids' => [1]], ['filename' => 'daily']))
        ->assertOk()
        ->assertJsonPath('result.effect.name', 'daily.xlsx');

    expect(array_column($json['fields'], 'value'))->toBe(['daily', 'xlsx'])
        ->and($response->json('result.effect.url'))->toContain('/lathe/api/exports/');
});

it('writes an xlsx file with every value as a text cell, so "=" never starts a formula', function () {
    Order::whereKey(1)->update(['number' => '=1+1']);

    $this->postJson('/lathe/api/resources/orders/action', exportBody(['mode' => 'ids', 'ids' => [1]], ['filename' => 'orders', 'type' => 'xlsx']))->assertOk();
    $path = Storage::disk('local')->path((string) Export::firstOrFail()->file_name);
    $zip = new ZipArchive;
    $zip->open($path);
    $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    $reader = new Reader;
    $reader->open($path);
    $rows = [];

    foreach ($reader->getSheetIterator() as $sheetRows) {
        foreach ($sheetRows->getRowIterator() as $row) {
            $rows[] = array_map(fn ($cell) => $cell->getValue(), $row->cells);
        }
    }

    $reader->close();

    // The reader turns any text starting with "=" into a formula cell, so the sheet XML says what was written.
    expect($sheet)->not->toContain('<f>')
        ->and($sheet)->toMatch('#<c r="A2"[^>]* t="(s|inlineStr)"#')
        ->and($rows)->toHaveCount(2)
        ->and($rows[1][0])->toBe('=1+1')
        ->and($rows[1][3])->toBe('1234.50');
});

it('queues on the connection, queue, tries, timeout and chunk size queueable sets', function () {
    Queue::fake();
    ExportDeclared::$actions = [ExportAction::make()->queueable('database', 'exports', 3, 120, 50)];

    $this->postJson('/lathe/api/resources/declared-export-orders/action', exportBody(['mode' => 'ids', 'ids' => range(1, 501)]))->assertOk();

    Queue::assertPushed(ProcessExport::class, fn (ProcessExport $job) => $job->connection === 'database'
        && $job->queue === 'exports'
        && $job->tries === 3
        && $job->timeout === 120
        && $job->chunkSize === 50);
});

it('writes two paths for two users exporting orders, each downloading as orders.csv', function () {
    $editor = User::where('email', 'editor@lathephp.com')->firstOrFail();

    $first = $this->postJson('/lathe/api/resources/orders/action', exportBody(['mode' => 'ids', 'ids' => [1]]))->assertOk();
    $this->actingAs($editor);
    $second = $this->postJson('/lathe/api/resources/orders/action', exportBody(['mode' => 'ids', 'ids' => [2]]))->assertOk();
    $exports = Export::orderBy('id')->get();

    expect($exports)->toHaveCount(2)
        ->and($exports[0]->file_name)->not->toBe($exports[1]->file_name)
        ->and($exports[0]->file_name)->toMatch('#^lathe-exports/[0-9A-Z]{26}-orders\.csv$#')
        ->and($exports[1]->file_name)->toMatch('#^lathe-exports/[0-9A-Z]{26}-orders\.csv$#')
        ->and($exports[1]->user_id)->toBe((string) $editor->id)
        ->and($exports[1]->only(['file_disk', 'total_rows', 'processed_rows', 'successful_rows', 'failed_at', 'exception']))
        ->toBe(['file_disk' => 'local', 'total_rows' => 1, 'processed_rows' => 1, 'successful_rows' => 1, 'failed_at' => null, 'exception' => null])
        ->and($exports[1]->completed_at)->not->toBeNull();

    $this->get($second->json('result.effect.url'))->assertOk()->assertDownload('orders.csv');
    $this->actingAs($this->admin)->get($first->json('result.effect.url'))->assertOk()->assertDownload('orders.csv');
});

it('updates processed_rows every exports.chunk_size rows, then at the end', function () {
    Queue::fake();
    config(['lathe.exports.chunk_size' => 100]);
    $this->postJson('/lathe/api/resources/orders/action', exportBody(['mode' => 'ids', 'ids' => range(1, 501)]))->assertOk();
    $seen = [];
    Export::updated(function (Export $export) use (&$seen) {
        if ($export->wasChanged('processed_rows')) {
            $seen[] = $export->processed_rows;
        }
    });

    runPushedExport();

    expect($seen)->toBe([100, 200, 300, 400, 500, 501])
        ->and(Export::firstOrFail()->only(['total_rows', 'successful_rows']))->toBe(['total_rows' => 501, 'successful_rows' => 501]);
});

it('offers Export on each row by the export ability, not runAction', function () {
    $this->actingAs(User::where('email', 'editor@lathephp.com')->firstOrFail());

    $json = $this->getJson('/lathe/api/resources/orders')->assertOk()->json();

    expect($json['table']['rows'][0]['actions'])->toBe(['export']);
});
