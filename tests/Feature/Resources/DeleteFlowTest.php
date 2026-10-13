<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Lathe\Events\ResourceDeleted;
use Lathe\Events\ResourceForceDeleted;
use Lathe\Events\ResourceRestored;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Tests\Fixtures\Policies\EmptyPolicy;
use Workbench\App\Models\Order;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => in_array($user->email, ['admin@lathephp.com', 'editor@lathephp.com'], true));
    DeleteFlowLog::$entries = [];
    DeleteFlowLog::$throwIn = null;
});

/** Collects each step of a delete, force delete or restore with the transaction level it ran at. */
class DeleteFlowLog
{
    /** @var list<string> */
    public static array $entries = [];

    public static ?string $throwIn = null;

    public static function add(string $step): void
    {
        self::$entries[] = $step.'@'.DB::transactionLevel();

        if (self::$throwIn === $step) {
            throw new RuntimeException("{$step} failed");
        }
    }
}

class DeleteFlowPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function delete(User $user, mixed $post): bool
    {
        DeleteFlowLog::add('ability');

        return true;
    }

    public function forceDelete(User $user, mixed $post): bool
    {
        DeleteFlowLog::add('ability');

        return true;
    }

    public function restore(User $user, mixed $post): bool
    {
        DeleteFlowLog::add('ability');

        return true;
    }
}

class LoggedPost extends Resource
{
    public static ?string $policy = DeleteFlowPolicy::class;

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Post::class)->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }

    public static function beforeDelete(LatheRequest $request, Model $model): void
    {
        DeleteFlowLog::add('beforeDelete');
    }

    public static function afterDelete(LatheRequest $request, Model $model): void
    {
        DeleteFlowLog::add('afterDelete');
    }

    public static function beforeForceDelete(LatheRequest $request, Model $model): void
    {
        DeleteFlowLog::add('beforeForceDelete');
    }

    public static function afterForceDelete(LatheRequest $request, Model $model): void
    {
        DeleteFlowLog::add('afterForceDelete');
    }

    public static function beforeRestore(LatheRequest $request, Model $model): void
    {
        DeleteFlowLog::add('beforeRestore');
    }

    public static function afterRestore(LatheRequest $request, Model $model): void
    {
        DeleteFlowLog::add('afterRestore');
    }
}

/** Orders with a global scope that hides order 1. */
class ScopedOrder extends Order
{
    protected $table = 'orders';

    protected static function booted(): void
    {
        static::addGlobalScope('notFirst', fn (Builder $query) => $query->where('id', '>', 1));
    }
}

class ScopedOrders extends Resource
{
    public static ?string $policy = DeleteFlowPolicy::class;

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(ScopedOrder::class);
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }
}

class DeleteFlowUuidRecord extends Model
{
    use HasUuids;

    protected $table = 'delete_flow_uuid_records';
}

class DeleteFlowUlidRecord extends Model
{
    use HasUlids;

    protected $table = 'delete_flow_ulid_records';
}

class DeleteFlowUuidRecords extends Resource
{
    public static ?string $policy = DeleteFlowPolicy::class;

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(DeleteFlowUuidRecord::class);
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }
}

class DeleteFlowUlidRecords extends Resource
{
    public static ?string $policy = DeleteFlowPolicy::class;

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(DeleteFlowUlidRecord::class);
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }
}

function signInForDelete(string $email): void
{
    test()->actingAs(User::where('email', $email)->firstOrFail());
}

it('deletes an order for the admin and points back at the list', function () {
    signInForDelete('admin@lathephp.com');

    $this->deleteJson('/lathe/api/resources/orders/1')->assertOk()->assertExactJson(['redirect' => 'orders']);

    expect(Order::find(1))->toBeNull();
});

it('refuses the editor a delete and keeps the order', function () {
    signInForDelete('editor@lathephp.com');

    $this->deleteJson('/lathe/api/resources/orders/1')->assertForbidden();

    expect(Order::find(1))->not->toBeNull();
});

it('runs each flow in order: ability, before hook and event in the transaction, after hook past commit', function (string $method, string $url, string $model, string $event, string $hook) {
    signInForDelete('admin@lathephp.com');
    Lathe::registerResources(LoggedPost::class);
    Post::{$model}(fn () => DeleteFlowLog::add('write'));
    Event::listen($event, fn () => DeleteFlowLog::add('event'));

    $this->json($method, $url)->assertOk()->assertExactJson(['redirect' => 'logged-posts']);

    expect(DeleteFlowLog::$entries)->toBe(['ability@0', "before{$hook}@1", 'write@1', 'event@1', "after{$hook}@0"]);
})->with([
    'delete' => ['DELETE', '/lathe/api/resources/logged-posts/1', 'deleted', ResourceDeleted::class, 'Delete'],
    'force delete' => ['DELETE', '/lathe/api/resources/logged-posts/195/force', 'forceDeleted', ResourceForceDeleted::class, 'ForceDelete'],
    'restore' => ['PUT', '/lathe/api/resources/logged-posts/195/restore', 'restored', ResourceRestored::class, 'Restore'],
]);

it('answers 500 and keeps the row when beforeDelete throws', function () {
    signInForDelete('admin@lathephp.com');
    Lathe::registerResources(LoggedPost::class);
    DeleteFlowLog::$throwIn = 'beforeDelete';

    $this->deleteJson('/lathe/api/resources/logged-posts/1')->assertStatus(500);

    expect(Post::find(1))->not->toBeNull();
});

it('rolls the delete back when a ResourceDeleted listener throws', function () {
    signInForDelete('admin@lathephp.com');
    Event::listen(ResourceDeleted::class, fn () => throw new RuntimeException('listener failed'));

    $this->deleteJson('/lathe/api/resources/orders/1')->assertStatus(500);

    expect(Order::find(1))->not->toBeNull();
});

it('restores a trashed post and fires ResourceRestored once', function () {
    signInForDelete('admin@lathephp.com');
    Event::fake([ResourceRestored::class]);

    $this->putJson('/lathe/api/resources/posts/195/restore')->assertOk()->assertExactJson(['redirect' => 'posts']);

    expect(Post::find(195))->not->toBeNull();
    Event::assertDispatchedTimes(ResourceRestored::class, 1);
});

it('force deletes a trashed post for good and fires ResourceForceDeleted once', function () {
    signInForDelete('admin@lathephp.com');
    Event::fake([ResourceForceDeleted::class]);

    $this->deleteJson('/lathe/api/resources/posts/195/force')->assertOk()->assertExactJson(['redirect' => 'posts']);

    expect(Post::withTrashed()->find(195))->toBeNull();
    Event::assertDispatchedTimes(ResourceForceDeleted::class, 1);
});

it('answers 403 without the ability and keeps the post', function (string $method, string $url, int $id, bool $emptyPolicy) {
    signInForDelete('admin@lathephp.com');

    if ($emptyPolicy) {
        Gate::policy(Post::class, EmptyPolicy::class);
    }

    $this->json($method, $url)->assertForbidden();

    expect(Post::withTrashed()->find($id)?->trashed())->toBe($id === 195);
})->with([
    'delete, which the post policy denies' => ['DELETE', '/lathe/api/resources/posts/1', 1, false],
    'force delete' => ['DELETE', '/lathe/api/resources/posts/195/force', 195, true],
    'restore' => ['PUT', '/lathe/api/resources/posts/195/restore', 195, true],
]);

it('answers 404 for an id outside a global scope', function () {
    signInForDelete('admin@lathephp.com');
    Lathe::registerResources(ScopedOrders::class);

    $this->deleteJson('/lathe/api/resources/scoped-orders/1')->assertNotFound();
    expect(Order::find(1))->not->toBeNull()
        ->and(DeleteFlowLog::$entries)->toBe([]);

    $this->deleteJson('/lathe/api/resources/scoped-orders/2')->assertOk();
    expect(Order::find(2))->toBeNull();
});

it('gives post rows their trashed state and restore and force delete abilities, but not order rows', function () {
    signInForDelete('admin@lathephp.com');

    $post = $this->getJson('/lathe/api/resources/posts?trashed=only')->assertOk()->json('table.rows.0');
    $order = $this->getJson('/lathe/api/resources/orders')->json('table.rows.0');

    expect($post['trashed'])->toBeTrue()
        ->and($post['abilities'])->toBe(['view' => true, 'update' => false, 'delete' => false, 'replicate' => false, 'restore' => true, 'forceDelete' => true])
        ->and($order)->not->toHaveKey('trashed')
        ->and($order['abilities'])->toBe(['view' => true, 'update' => true, 'delete' => true, 'replicate' => false]);
});

it('answers 404 before any query for an id the key cannot hold', function (string $url, string $table) {
    signInForDelete('admin@lathephp.com');
    Lathe::registerResources(DeleteFlowUuidRecords::class, DeleteFlowUlidRecords::class);
    $queries = [];
    DB::listen(function (QueryExecuted $event) use (&$queries) {
        $queries[] = $event->sql;
    });

    $this->deleteJson($url)->assertNotFound();

    expect(array_filter($queries, fn (string $sql) => str_contains($sql, $table)))->toBe([]);
})->with([
    'a word for an integer key' => ['/lathe/api/resources/posts/abc', 'posts'],
    'a number past PHP_INT_MAX' => ['/lathe/api/resources/orders/99999999999999999999', 'orders'],
    'the bare saved tabs path' => ['/lathe/api/resources/orders/saved-tabs', 'orders'],
    'a word for a uuid key' => ['/lathe/api/resources/delete-flow-uuid-records/abc', 'delete_flow_uuid_records'],
    'a uuid for a ulid key' => ['/lathe/api/resources/delete-flow-ulid-records/'.Str::uuid(), 'delete_flow_ulid_records'],
]);

it('deletes a record by its uuid or ulid key', function (string $class, string $resource, Closure $idColumn) {
    signInForDelete('admin@lathephp.com');
    Lathe::registerResources($resource);
    $table = (new $class)->getTable();
    Schema::create($table, function (Blueprint $blueprint) use ($idColumn) {
        $idColumn($blueprint);
        $blueprint->timestamps();
    });
    $record = $class::create();

    $this->deleteJson('/lathe/api/resources/'.$resource::uriKey().'/'.$record->getKey())->assertOk();

    expect($class::find($record->getKey()))->toBeNull();
})->with([
    'uuid' => [DeleteFlowUuidRecord::class, DeleteFlowUuidRecords::class, fn (Blueprint $table) => $table->uuid('id')->primary()],
    'ulid' => [DeleteFlowUlidRecord::class, DeleteFlowUlidRecords::class, fn (Blueprint $table) => $table->ulid('id')->primary()],
]);
