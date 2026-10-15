<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lathe\Actions\BulkRun;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    (new DemoSeeder)->forOrders(1001)->run();
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    config(['queue.default' => 'database']);
});

it('bulk_delete_all_runs_in_chunks_within_memory_cap', function () {
    $log = [];
    DB::listen(function ($query) use (&$log) {
        $log[] = $query->sql;
    });

    $response = $this->deleteJson('/lathe/api/resources/orders/batch', ['selection' => ['mode' => 'all']])->assertOk();
    $run = BulkRun::sole();
    expect($response->json())->toBe(['deleted' => 0, 'skipped' => 0, 'bulk_run' => ['id' => $run->id, 'total' => 1001]])
        ->and(Order::count())->toBe(1001);

    // The worker's --memory matches the test process, which coverage runs push past 128 MB.
    $this->artisan('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--sleep' => 0, '--memory' => 2048])->assertSuccessful();

    $reads = array_filter($log, fn (string $sql) => str_starts_with($sql, 'select * from "orders"') || str_starts_with($sql, 'select * from `orders`'));
    expect(Order::count())->toBe(0)
        ->and(DB::table('job_batches')->value('total_jobs'))->toBe(7)
        ->and($reads)->not->toBeEmpty()
        ->and(array_filter($reads, fn (string $sql) => ! str_contains($sql, 'limit 200')))->toBeEmpty();

    $this->getJson("/lathe/api/bulk-runs/{$run->id}")
        ->assertExactJson(['status' => 'finished', 'total' => 1001, 'processed' => 1001, 'failed' => 0, 'skipped' => 0]);
})->group('regression');
