<?php

namespace Lathe\Tests\Feature\BulkRuns;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lathe\Actions\Action;
use Lathe\Actions\ActionFields;
use Lathe\Actions\ActionResult;
use Lathe\Actions\BulkRun;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\Order;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class GuardedQueuedShip extends Action implements ShouldQueue
{
    public function handle(ActionFields $fields, Collection $models): ActionResult
    {
        return ActionResult::message('Shipped.');
    }
}

/** Orders under the workbench OrderPolicy, which gives runAction to the admin only. */
class GuardedOrders extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }

    public function actions(LatheRequest $request): array
    {
        return [GuardedQueuedShip::make()];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    (new DemoSeeder)->forOrders(1001)->run();
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(GuardedOrders::class);
    Bus::fake();
    $this->actingAs(User::where('email', 'editor@lathephp.com')->firstOrFail());
});

it('answers 403 to a mode all action the user may run on no row before it queues', function () {
    $this->postJson('/lathe/api/resources/orders/action', ['action' => 'mark-shipped', 'selection' => ['mode' => 'all'], 'fields' => []])
        ->assertForbidden();

    expect(BulkRun::count())->toBe(0);
    Bus::assertNothingBatched();
});

it('answers 403 to a queued action on one row the user may not run', function () {
    $this->postJson('/lathe/api/resources/guarded-orders/action', ['action' => 'guarded-queued-ship', 'selection' => ['mode' => 'ids', 'ids' => [1]], 'fields' => []])
        ->assertForbidden();

    expect(BulkRun::count())->toBe(0);
    Bus::assertNothingBatched();
});

it('answers 403 to a batch delete over the inline limit when the policy denies every row', function () {
    // The editor's index shows published posts only, so these are the rows the selection holds.
    $now = now();
    DB::table('posts')->insert(array_map(fn (int $i) => ['title' => "Post {$i}", 'status' => 'published', 'body' => 'Body', 'created_at' => $now, 'updated_at' => $now], range(1, 600)));

    expect(Post::where('status', 'published')->count())->toBeGreaterThan(500);

    $this->deleteJson('/lathe/api/resources/posts/batch', ['selection' => ['mode' => 'all']])
        ->assertForbidden();

    expect(BulkRun::count())->toBe(0);
    Bus::assertNothingBatched();
});
