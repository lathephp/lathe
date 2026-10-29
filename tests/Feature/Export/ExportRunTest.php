<?php

namespace Lathe\Tests\Feature\Export;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Lathe\Actions\Export\Export;
use Lathe\Actions\Export\ExportAction;
use Lathe\Actions\Export\ExportReady;
use Lathe\Actions\Export\ProcessExport;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\ResourceDefinition;
use RuntimeException;
use Throwable;
use Workbench\App\Lathe\Resources\Order as OrderResource;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class RunExportDeclared
{
    /** @var list<ExportAction> */
    public static array $actions = [];
}

class RunExportOrders extends OrderResource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canonical(false);
    }

    public function actions(LatheRequest $request): array
    {
        return RunExportDeclared::$actions;
    }
}

/**
 * An export that counts more rows than an XLSX sheet holds.
 */
class HugeExport extends ExportAction
{
    protected function countRows(Builder $query): int
    {
        return 1_048_576;
    }
}

/**
 * Starts a queued export of 501 orders and answers its run.
 */
function startQueuedExport(mixed $test, string $resource = 'orders', string $type = 'csv'): Export
{
    $test->postJson("/lathe/api/resources/{$resource}/action", ['action' => 'export', 'selection' => ['mode' => 'ids', 'ids' => range(1, 501)], 'fields' => ['filename' => 'orders', 'type' => $type]])
        ->assertOk();

    return Export::latest('id')->firstOrFail();
}

/**
 * Runs the pushed job as a worker does: handle, and failed() when it throws.
 */
function workPushedExport(): ?Throwable
{
    $job = Queue::pushed(ProcessExport::class)->last();

    try {
        app()->call([$job, 'handle']);
    } catch (Throwable $e) {
        $job->failed($e);

        return $e;
    }

    return null;
}

function downloadUrl(Export $export, int $hours = 24): string
{
    return URL::temporarySignedRoute('lathe.api.exports.download', now()->addHours($hours), ['export' => $export->id], absolute: false);
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    app(DemoSeeder::class)->forOrders(600)->__invoke();
    Gate::define('viewLathe', fn () => true);
    Storage::fake('local');
    Queue::fake();
    Notification::fake();
    RunExportDeclared::$actions = [];
    Lathe::registerResources(RunExportOrders::class);
    $this->admin = User::where('email', 'admin@lathephp.com')->firstOrFail();
    $this->editor = User::where('email', 'editor@lathephp.com')->firstOrFail();
    $this->actingAs($this->admin);
});

it('reports a queued run to its owner only, with a signed download link once finished', function () {
    // The notification's link and the status link are signed apart; a second between them changed expires.
    $this->freezeTime();
    $export = startQueuedExport($this);

    $this->getJson("/lathe/api/exports/{$export->id}")->assertOk()->assertExactJson([
        'id' => $export->id, 'status' => 'waiting', 'total_rows' => 0, 'processed_rows' => 0, 'download_url' => null, 'exception' => null,
    ]);
    $this->actingAs($this->editor)->getJson("/lathe/api/exports/{$export->id}")->assertNotFound();
    $this->actingAs($this->admin)->getJson('/lathe/api/exports/999')->assertNotFound();

    workPushedExport();
    $json = $this->getJson("/lathe/api/exports/{$export->id}")->assertOk()->json();

    expect($json)->toMatchArray(['id' => $export->id, 'status' => 'finished', 'total_rows' => 501, 'processed_rows' => 501, 'exception' => null])
        ->and($json['download_url'])->toContain("/lathe/api/exports/{$export->id}/download?expires=");
    $this->get($json['download_url'])->assertOk()->assertDownload('orders.csv');
    Notification::assertSentTo($this->admin, ExportReady::class, fn (ExportReady $notification) => $notification->toArray()['type'] === 'success'
        && $notification->toArray()['message'] === 'Your export is ready for download.'
        && $notification->toArray()['actions'][0]['text'] === 'Download'
        && $notification->toArray()['actions'][0]['url'] === $json['download_url']
        && $notification->toArray()['actions'][0]['openInNewTab'] === true);
});

it('gives a download link with no host, so a link built under another app.url still downloads', function () {
    config(['app.url' => 'http://localhost']);
    $this->freezeTime();
    $export = startQueuedExport($this);
    workPushedExport();

    $url = (string) $this->getJson("/lathe/api/exports/{$export->id}")->json('download_url');

    expect($url)->toStartWith("/lathe/api/exports/{$export->id}/download?expires=")
        ->and($url)->not->toContain('://');
    $this->get('http://127.0.0.1:8000'.$url)->assertOk()->assertDownload('orders.csv');
    $this->get('http://127.0.0.1:8000'.substr($url, 0, -1).(str_ends_with($url, 'a') ? 'b' : 'a'))->assertForbidden();
    Notification::assertSentTo($this->admin, ExportReady::class, fn (ExportReady $notification) => $notification->toArray()['actions'][0]['url'] === $url);
});

it('refuses a download unsigned, to another user, after it expires, before it finishes and once the file is gone', function () {
    $export = startQueuedExport($this);

    $this->get(downloadUrl($export))->assertNotFound();
    workPushedExport();
    $url = downloadUrl($export);

    $this->get("/lathe/api/exports/{$export->id}/download")->assertForbidden();
    $this->actingAs($this->editor)->get($url)->assertForbidden();
    $this->actingAs($this->admin)->get($url)->assertOk()->assertDownload('orders.csv');
    $this->travel(25)->hours();
    $this->get($url)->assertForbidden();
    $this->travelBack();
    Storage::disk('local')->delete((string) $export->fresh()?->file_name);
    $this->get(downloadUrl($export))->assertNotFound();
});

it('deletes the partial file of a failing job, records why and notifies an error', function () {
    RunExportDeclared::$actions = [ExportAction::make()->withFormat(fn (Order $order) => $order->id === 450 ? throw new RuntimeException('Order 450 broke.') : ['Ref' => $order->number])];
    $export = startQueuedExport($this, 'run-export-orders');
    // A file an earlier attempt left behind.
    Storage::disk('local')->put((string) $export->file_name, 'partial');

    $error = workPushedExport();
    $export->refresh();

    expect($error?->getMessage())->toBe('Order 450 broke.')
        ->and(Storage::disk('local')->exists((string) $export->file_name))->toBeFalse()
        ->and($export->failed_at)->not->toBeNull()
        ->and($export->completed_at)->toBeNull()
        ->and($export->exception)->toBe('Order 450 broke.');
    $this->getJson("/lathe/api/exports/{$export->id}")->assertJson(['status' => 'failed', 'download_url' => null, 'exception' => 'Order 450 broke.']);
    Notification::assertSentTo($this->admin, ExportReady::class, fn (ExportReady $notification) => $notification->toArray()['type'] === 'error'
        && $notification->toArray()['message'] === 'Your export failed.');
});

it('fails an xlsx export of more than 1,048,575 rows before writing a byte', function () {
    RunExportDeclared::$actions = [HugeExport::make()];
    $export = startQueuedExport($this, 'run-export-orders', 'xlsx');

    $error = workPushedExport();
    $export->refresh();

    expect($error?->getMessage())->toBe('An XLSX file holds at most 1,048,575 rows.')
        ->and($export->exception)->toBe('An XLSX file holds at most 1,048,575 rows.')
        ->and($export->processed_rows)->toBe(0)
        ->and($export->failed_at)->not->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('prunes an export older than exports.prune_after_hours with its file', function () {
    $old = Export::create(['user_id' => (string) $this->admin->id, 'file_disk' => 'local', 'file_name' => 'lathe-exports/01OLD-orders.csv']);
    $recent = Export::create(['user_id' => (string) $this->admin->id, 'file_disk' => 'local', 'file_name' => 'lathe-exports/01NEW-orders.csv']);
    $old->forceFill(['created_at' => now()->subHours(25)])->save();
    $recent->forceFill(['created_at' => now()->subHours(23)])->save();
    Storage::disk('local')->put('lathe-exports/01OLD-orders.csv', 'a');
    Storage::disk('local')->put('lathe-exports/01NEW-orders.csv', 'b');

    Artisan::call('model:prune', ['--model' => [Export::class]]);

    expect(Export::pluck('id')->all())->toBe([$recent->id])
        ->and(Storage::disk('local')->allFiles())->toBe(['lathe-exports/01NEW-orders.csv']);
});

it('finishes a queued export with notifications off, its run carrying the download link', function () {
    config(['lathe.notifications.enabled' => false]);
    $export = startQueuedExport($this);

    workPushedExport();

    $this->getJson("/lathe/api/exports/{$export->id}")->assertOk()->assertJson(['status' => 'finished', 'processed_rows' => 501]);
    expect($this->getJson("/lathe/api/exports/{$export->id}")->json('download_url'))->toContain('/download?expires=');
    $this->get("/lathe/runs/export/{$export->id}")->assertOk();
    Notification::assertNothingSent();
});

it('fails lathe:doctor when exports.disk names a disk that is not configured', function () {
    config(['lathe.exports.disk' => 'missing-disk']);

    $this->artisan('lathe:doctor')
        ->expectsOutputToContain('Disk missing-disk named by exports.disk is not defined in config/filesystems.php.')
        ->assertFailed();
});
