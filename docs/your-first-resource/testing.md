---
title: Testing
description: Test your panel from your own test suite with the Lathe test kit, the same one Lathe tests itself with.
order: 7
---

# Testing

Lathe ships a test kit in the `Lathe\Testing` namespace. It sends the same JSON requests the panel's pages send, so a test exercises the whole stack: the route, the policy, the query, the validation and the response. You do not build URLs or payloads by hand.

The examples use [Pest](https://pestphp.com).

## Set up

The kit sends real requests, so your test boots the full app like any feature test. Sign in a user, make sure the user can open the panel, and then call the kit.

```php
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Lathe\Testing\LatheTest;

uses(RefreshDatabase::class);

beforeEach(function () {
    Gate::define('viewLathe', fn () => true);

    LatheTest::actingAs(User::factory()->create());
});
```

Two things in this setup are easy to miss.

**The gate.** Every panel request needs a signed-in user who passes the `viewLathe` gate, in every environment, including `testing`. The gate in your `LatheServiceProvider` starts with an empty email list, so it admits nobody and every request gets a 403. Redefine the gate in the test, as above, or add the test user's email to the list. The test runs after the app has booted, so its `Gate::define()` wins.

**The policy.** A Resource with no policy, or a policy without the method for an action, denies that action. A 403 in a test usually means the test user lacks a policy method. See [Policies](../security/policies.md).

`LatheTest::actingAs()` signs in on the panel guard, which is the guard you set in `config/lathe.php`. Laravel's own `$this->actingAs()` signs in on the default guard, so use `LatheTest::actingAs()` in panel tests.

## A first test

`LatheTest::resource()` takes a Resource class or its URI key and returns a tester. Each method of the tester sends one request and returns Laravel's `TestResponse`, so you chain the usual assertions.

```php
use App\Lathe\Resources\Post;
use App\Models\Post as PostModel;

it('lists the posts', function () {
    PostModel::factory()->count(3)->create();

    LatheTest::resource(Post::class)
        ->index()
        ->assertOk()
        ->assertJsonCount(3, 'table.rows');
});

it('refuses a post without a title', function () {
    LatheTest::resource(Post::class)
        ->store([])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});

it('creates and deletes a post', function () {
    LatheTest::resource(Post::class)
        ->store(['title' => 'Hello'])
        ->assertCreated();

    $post = PostModel::where('title', 'Hello')->firstOrFail();

    LatheTest::resource(Post::class)->delete($post->getKey())->assertOk();

    expect(PostModel::find($post->getKey()))->toBeNull();
});
```

The URI key of a Resource is its class name in plural kebab case, so `Post` is `posts` and `OrderItem` is `order-items`. `LatheTest::resource('posts')` is the same as `LatheTest::resource(Post::class)`.

## Read and write records

These methods are on the tester that `LatheTest::resource()` returns.

| Method | What it sends |
|---|---|
| `index(array $query = [])` | The index. The query takes `q` for search, `sort`, `per_page`, `filter` and the other URL state. |
| `store(array $data)` | A create. |
| `update(string\|int $id, array $data)` | An update. |
| `delete(string\|int $id)` | A delete. |
| `forceDelete(string\|int $id)` | A permanent delete of a trashed record. |
| `restore(string\|int $id)` | A restore of a trashed record. |
| `creationFields(array $query = [])` | The create form's fields, default values and layout. |
| `updateFields(string\|int $id)` | The edit form's fields with the record's values. |
| `replicateFields(string\|int $id)` | The create form filled from a copy of a record. |
| `peek(string\|int $id)` | The peek card of a record. |
| `preview(string\|int $id)` | The preview of a record. |

```php
LatheTest::resource('posts')->index(['q' => 'hello', 'per_page' => 50])->assertOk();
```

To test the rows a user is allowed to see, sign in as that user and read the index. A row that `indexQuery()` hides is missing from `table.rows`.

### Batch calls

The batch methods take a selection. A selection is an array with a `mode`. In mode `ids`, name the keys. In mode `all`, the selection is the current search and filters, less the keys you list in `exclusions`.

```php
$ids = PostModel::factory()->count(3)->create()->modelKeys();

LatheTest::resource('posts')->batchDelete(['mode' => 'ids', 'ids' => $ids])->assertOk();
```

| Method | What it does |
|---|---|
| `batchDelete(array $selection)` | Deletes the selection. |
| `batchForceDelete(array $selection)` | Deletes the selection for good. |
| `batchRestore(array $selection)` | Restores the selection. |

> Note: A selection in mode `ids` needs at least one key. An empty list is refused with a 422, so a test cannot pass by acting on nothing.

## Test actions

`actions()` lists the actions the user may see, and `actionForm()` reads one action's form. `runAction()` runs an action on a selection:

```php
$order = Order::factory()->create(['status' => 'paid']);

LatheTest::resource('orders')
    ->runAction('mark-shipped', ['mode' => 'ids', 'ids' => [$order->getKey()]])
    ->assertOk();

expect($order->fresh()->status)->toBe('shipped');
```

Pass the values of the action's own fields as the third argument. They go out under `fields`, as the modal sends them:

```php
LatheTest::resource('posts')->runAction('assign-author', $selection, [
    'author_id' => $author->getKey(),
]);
```

If any value is an `UploadedFile`, the tester sends a multipart request, as a browser does. Multipart turns scalars into strings, so `true` arrives as `"1"`.

| Method | What it sends |
|---|---|
| `actions(string $display = 'index', array $query = [])` | The visible actions. `$display` is `index`, `detail` or `table-row`. |
| `actionForm(string $action, array $query = [])` | One action's form. |
| `runAction(string $action, array $selection, array $fields = [])` | A run, as JSON or multipart. |
| `postAction(string $action, array $selection, array $fields = [])` | A run, always as JSON. |
| `actionDependencies(string $action, string $field, array $values = [], array $dirty = [])` | The lookup for a dependent field of the action form. |
| `actionAssociatable(string $action, string $field, array $query = [])` | One page of a relation field's picker in the action form. |
| `startActionUpload(string $action, string $field, array $body)` | Opens a chunked upload for a file field. |

The run response holds the action's result, so assert on it with `assertJsonPath('result.message', ...)`.

## Test relations

`relation()` returns a tester for one relation of one record. Pass the relationship name and the record's key:

```php
$tags = LatheTest::resource('products')->relation('tags', $product->getKey());

$tags->attach($tag->getKey(), ['position' => 1])->assertCreated();

expect($product->tags()->count())->toBe(1);

$tags->updatePivot($tag->getKey(), ['position' => 2])->assertOk();
$tags->detach($tag->getKey())->assertOk();
```

| Method | What it sends |
|---|---|
| `index(array $params = [])` | The related rows. They are in the `rows` key of the response. |
| `attach(string\|int $relatedId, array $pivot = [])` | An attach, with the pivot values under `pivot`. |
| `detach(string\|int $relatedId)` | A detach. |
| `updatePivot(string\|int $relatedId, array $pivot)` | A change of the pivot values. |
| `pivotFields()` | The pivot form for the attach modal. |
| `pivotFieldValues(string\|int $relatedId)` | The pivot form of an attached row, with its values. |

## Test lenses and widgets

`forLens()` returns a tester that sends its actions and batch calls, and reads its widgets, through a lens. `lens()` is another name for the same call.

```php
$pending = LatheTest::resource('orders')->forLens('pending-orders');

$pending->actions()->assertOk();
```

A resource tester also reads widgets. A widget is computed by its own request, so assert on the computed value and not on the list. The params go out as the query string. Widgets read `range`, `timezone`, `filters` and `resourceId`.

```php
LatheTest::resource('orders')
    ->widget('revenue', ['range' => 30])
    ->assertOk();
```

| Method | What it sends |
|---|---|
| `widgets(array $params = [])` | The widgets the user may see, without their values. |
| `widget(string $widget, array $params = [])` | One widget's value. |

## Test dashboards

```php
LatheTest::dashboards()->assertOk();

$main = LatheTest::dashboard('main');

$main->show()->assertOk();
$main->widgets()->assertOk();
$main->widget('revenue', ['range' => 90])->assertOk();
```

`LatheTest::dashboards()` lists the dashboards the user may see. `LatheTest::dashboard($uriKey)` returns a tester with `show()`, `widgets()` and `widget()`.

## Helpers for your test class

The `InteractsWithLathe` trait adds helpers to a test class. In Pest, add it with `uses()`:

```php
use Lathe\Testing\InteractsWithLathe;

uses(InteractsWithLathe::class);
```

| Helper | What it does |
|---|---|
| `registerResources(string ...$classes)` | Registers Resources for the test without a provider. |
| `flushLathe()` | Forgets every registered Resource and the cached list. Call it in `afterEach()`. |
| `latheRequest(string $uri = '/lathe/api', string $method = 'GET', array $parameters = [])` | Builds a `LatheRequest` without sending it, for unit tests of code that takes one. |
| `assertQueryCount(int $expected, Closure $request)` | Fails unless the closure runs exactly that many queries. The failure lists the SQL. |
| `assertScoped(string $model, Closure $request)` | Fails when a query on the model's table runs without the column its global scopes filter on. |

`assertQueryCount()` catches an N+1 problem. A page of 10 rows and a page of 100 rows should run the same number of queries:

```php
it('loads the index in a fixed number of queries', function () {
    PostModel::factory()->count(20)->create();

    $this->assertQueryCount(5, fn () => LatheTest::resource(Post::class)->index());
});
```

The number 5 is an example. Run the test once, read the real count in the failure message, and set that number.

`assertScoped()` protects a multi-tenant Resource. If a global scope filters on `tenant_id`, a query that forgets the column fails the test:

```php
$this->assertScoped(PostModel::class, fn () => LatheTest::resource(Post::class)->index());
```

## Test a plugin

If you write a plugin and test it alone, extend `Lathe\Testing\TestCase`. It builds on Orchestra Testbench and registers the Inertia, Fortify and Lathe providers. It defines the `viewLathe` gate to admit every signed-in user, turns on `Model::preventLazyLoading()`, and calls `flushLathe()` after each test. It already uses `InteractsWithLathe`.

```php
use Lathe\Testing\TestCase;

uses(TestCase::class)->in('Feature');
```

A test of the gate itself redefines it with `Gate::define('viewLathe', ...)`.

## What you see

Run `php artisan test` or `vendor/bin/pest`. Each test that uses the kit passes or fails in the terminal like any other feature test. A failure shows the status code and body of the panel response, so you see the validation errors, the 403 or the missing row that caused it.
