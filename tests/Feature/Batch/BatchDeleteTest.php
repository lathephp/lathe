<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lathe\ActionEvents\ActionEvent;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Uploads\FieldAttachment;
use Workbench\App\Models\Order;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    BatchPostHooks::$failOn = null;
    BatchAnyPolicy::$denied = [];
    Lathe::registerResources(BatchPosts::class, BatchAnyPosts::class, BatchHookPosts::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

class BatchPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function delete(User $user, mixed $post): bool
    {
        return true;
    }

    public function restore(User $user, mixed $post): bool
    {
        return true;
    }

    public function forceDelete(User $user, mixed $post): bool
    {
        return true;
    }
}

/** The same rules, narrowed to a subset by deleteAny. */
class BatchAnyPolicy extends BatchPolicy
{
    /** @var list<int> */
    public static array $denied = [];

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function deleteAny(User $user, Builder $query): Builder
    {
        return $query->whereNotIn('id', self::$denied);
    }
}

class BatchPostHooks
{
    public static ?int $failOn = null;
}

class BatchPosts extends Resource
{
    public static ?string $policy = BatchPolicy::class;

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Post::class)->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }
}

class BatchAnyPosts extends BatchPosts
{
    public static ?string $policy = BatchAnyPolicy::class;
}

class BatchHookPosts extends BatchPosts
{
    public static function beforeDelete(LatheRequest $request, Model $model): void
    {
        if ($model->getKey() === BatchPostHooks::$failOn) {
            throw new RuntimeException('no');
        }
    }
}

/**
 * @param  list<int|string>  $ids
 * @return array<string, mixed>
 */
function batchIds(array $ids, ?string $trashed = null): array
{
    return ['selection' => array_filter(['mode' => 'ids', 'ids' => $ids, 'trashed' => $trashed])];
}

it('deletes the three selected orders and writes one batch id for their audit rows', function () {
    $this->deleteJson('/lathe/api/resources/orders/batch', batchIds([1, 2, 3]))
        ->assertOk()->assertExactJson(['deleted' => 3, 'skipped' => 0]);

    $rows = ActionEvent::where('type', 'delete')->get();

    expect(Order::whereIn('id', [1, 2, 3])->count())->toBe(0)
        ->and($rows)->toHaveCount(3)
        ->and($rows->pluck('batch_id')->unique())->toHaveCount(1);
});

it('counts a row that is already trashed as skipped', function () {
    $this->deleteJson('/lathe/api/resources/batch-posts/batch', batchIds([1, 2, 195], 'with'))
        ->assertOk()->assertExactJson(['deleted' => 2, 'skipped' => 1]);

    expect(Post::find(1))->toBeNull()
        ->and(Post::onlyTrashed()->whereKey(1)->exists())->toBeTrue();
});

it('restores trashed posts and skips the live one', function () {
    $this->putJson('/lathe/api/resources/batch-posts/batch/restore', batchIds([1, 195, 196], 'with'))
        ->assertOk()->assertExactJson(['restored' => 2, 'skipped' => 1]);

    expect(Post::whereIn('id', [195, 196])->count())->toBe(2)
        ->and(ActionEvent::where('type', 'restore')->pluck('batch_id')->unique())->toHaveCount(1);
});

it('force deletes live and trashed posts and purges their attachments', function () {
    $attachment = FieldAttachment::create([
        'attachable_type' => (new Post)->getMorphClass(),
        'attachable_id' => '1',
        'attachment' => 'kept.png',
        'disk' => 'local',
        'url' => '/kept.png',
    ]);

    $this->deleteJson('/lathe/api/resources/batch-posts/batch/force', batchIds([1, 195], 'with'))
        ->assertOk()->assertExactJson(['deleted' => 2, 'skipped' => 0]);

    expect(Post::withTrashed()->whereIn('id', [1, 195])->count())->toBe(0)
        ->and(FieldAttachment::find($attachment->id))->toBeNull();
});

it('keeps the rows a deleteAny policy narrows out and counts them as skipped', function () {
    BatchAnyPolicy::$denied = [1, 2, 3, 4];

    $this->deleteJson('/lathe/api/resources/batch-any-posts/batch', batchIds(range(1, 10)))
        ->assertOk()->assertExactJson(['deleted' => 6, 'skipped' => 4]);

    expect(Post::whereIn('id', [1, 2, 3, 4])->count())->toBe(4)
        ->and(Post::whereIn('id', range(5, 10))->count())->toBe(0);
});

it('answers 403 on posts, whose policy denies delete', function () {
    $this->deleteJson('/lathe/api/resources/posts/batch', batchIds([1, 2]))->assertForbidden();

    expect(Post::whereIn('id', [1, 2])->count())->toBe(2);
});

it('deletes in mode all exactly what the index shows, reading the set 200 rows at a time', function () {
    // 450 paid orders: over two chunks, under the 500 rows a batch delete runs inline.
    Order::where('status', 'paid')->whereNotIn('id', Order::where('status', 'paid')->orderBy('id')->limit(450)->pluck('id'))->update(['status' => 'pending']);
    $paid = Order::where('status', 'paid')->pluck('id')->all();
    $kept = array_slice($paid, 0, 2);
    $log = [];
    DB::listen(function ($query) use (&$log) {
        $log[] = $query->sql;
    });

    $this->deleteJson('/lathe/api/resources/orders/batch', ['selection' => [
        'mode' => 'all', 'filters' => ['status' => 'paid'], 'exclusions' => $kept,
    ]])->assertOk()->assertExactJson(['deleted' => count($paid) - 2, 'skipped' => 0]);

    expect(Order::where('status', 'paid')->pluck('id')->all())->toBe($kept);

    $reads = array_filter($log, fn (string $sql) => str_starts_with($sql, 'select * from "orders"') || str_starts_with($sql, 'select * from `orders`'));
    expect($reads)->not->toBeEmpty()
        ->and(array_filter($reads, fn (string $sql) => ! str_contains($sql, 'limit 200')))->toBeEmpty();
});

it('rolls back only the row whose beforeDelete throws and counts it as skipped', function () {
    BatchPostHooks::$failOn = 2;

    $this->deleteJson('/lathe/api/resources/batch-hook-posts/batch', batchIds([1, 2, 3]))
        ->assertOk()->assertExactJson(['deleted' => 2, 'skipped' => 1]);

    expect(Post::whereIn('id', [1, 3])->count())->toBe(0)
        ->and(Post::find(2))->not->toBeNull();
});
