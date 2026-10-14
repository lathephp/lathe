<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Lathe\ActionEvents\ActionEvent;
use Lathe\Actions\BulkRun;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->admin = User::where('email', 'admin@lathephp.com')->firstOrFail();
    $this->editor = User::where('email', 'editor@lathephp.com')->firstOrFail();
});

function bulkRunOf(User $user, array $attributes = []): BulkRun
{
    return BulkRun::create($attributes + [
        'user_id' => $user->id,
        'resource_class' => 'Workbench\\App\\Lathe\\Resources\\Order',
        'action_name' => 'Mark Shipped',
        'batch_id' => (string) Str::orderedUuid(),
        'status' => 'running',
        'total' => 1001,
        'processed' => 400,
    ]);
}

it('reports the progress of a run to the user who started it', function () {
    $run = bulkRunOf($this->admin);

    $this->actingAs($this->admin)->getJson("/lathe/api/bulk-runs/{$run->id}")
        ->assertOk()
        ->assertExactJson(['status' => 'running', 'total' => 1001, 'processed' => 400, 'failed' => 0, 'skipped' => 0]);
});

it('cancels a run: the waiting events fail with Cancelled and the finished ones stay', function () {
    $run = bulkRunOf($this->admin);
    $events = fn (string $status, int $count) => ActionEvent::insert(array_fill(0, $count, [
        'batch_id' => $run->batch_id, 'type' => 'action-ran', 'status' => $status, 'created_at' => now(), 'updated_at' => now(),
    ]));
    $events('finished', 4);
    $events('waiting', 6);

    $this->actingAs($this->admin)->deleteJson("/lathe/api/bulk-runs/{$run->id}")->assertOk()->assertJson(['status' => 'cancelled']);

    expect($run->fresh()->status)->toBe('cancelled')
        ->and($run->fresh()->cancelled_at)->not->toBeNull()
        ->and(ActionEvent::where('status', 'finished')->count())->toBe(4)
        ->and(ActionEvent::where('status', 'failed')->where('exception', 'Cancelled')->count())->toBe(6);
});

it('answers 404 for another user and changes nothing', function () {
    $run = bulkRunOf($this->admin);

    $this->actingAs($this->editor)->getJson("/lathe/api/bulk-runs/{$run->id}")->assertNotFound();
    $this->actingAs($this->editor)->deleteJson("/lathe/api/bulk-runs/{$run->id}")->assertNotFound();

    expect($run->fresh()->status)->toBe('running');
});

it('prunes ended runs older than the action event age and keeps the rest', function () {
    $this->travelTo(now()->subDays(31));
    $old = bulkRunOf($this->admin, ['status' => 'finished']);
    $running = bulkRunOf($this->admin, ['status' => 'running']);
    $this->travelBack();
    $this->travelTo(now()->subDays(29));
    $recent = bulkRunOf($this->admin, ['status' => 'cancelled']);
    $this->travelBack();

    $this->artisan('model:prune', ['--model' => [BulkRun::class]])->assertSuccessful();

    expect(BulkRun::pluck('id')->sort()->values()->all())->toBe(collect([$running->id, $recent->id])->sort()->values()->all())
        ->and(BulkRun::find($old->id))->toBeNull();
});
