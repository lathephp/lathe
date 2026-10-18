<?php

use Illuminate\Auth\SessionGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use Lathe\Http\Middleware\HandleInertiaRequests;
use Lathe\Tests\Fixtures\Policies\EmptyPolicy;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => in_array($user->email, ['admin@lathephp.com', 'editor@lathephp.com'], true));
});

function signInAs(string $email): User
{
    $user = User::where('email', $email)->firstOrFail();
    test()->actingAs($user);

    return $user;
}

/**
 * Run a request and return the SQL it ran.
 *
 * @return list<string>
 */
function queriesDuring(Closure $request): array
{
    $queries = [];
    DB::listen(function ($event) use (&$queries) {
        $queries[] = $event->sql;
    });
    $request();

    return $queries;
}

it('lists 25 orders newest first from the API', function () {
    signInAs('admin@lathephp.com');

    $response = $this->getJson('/lathe/api/resources/orders?per_page=25')
        ->assertOk()
        ->assertHeader('X-Lathe-Version')
        ->assertHeader('X-Lathe-Assets')
        ->assertJsonStructure(['resource', 'table' => ['rows', 'columns', 'rowKey'], 'state']);

    $rows = $response->json('table.rows');
    expect($rows)->toHaveCount(25)
        ->and($rows[0]['id'])->toBe(10000)
        ->and(array_column($response->json('table.columns'), 'label'))->toBe(['Number', 'Customer', 'Status', 'Total', 'Placed'])
        ->and($rows[0]['cells']['total_cents']['value'])->toMatch('/^\d+\.\d{2}$/')
        ->and($rows[0]['cells']['placed_at']['value'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/')
        ->and($rows[0]['cells']['customer']['display'])->toBe(Order::find(10000)?->customer?->name);
});

it('draws Placed as a date-time and Status as labelled badges', function () {
    signInAs('admin@lathephp.com');

    $columns = collect($this->getJson('/lathe/api/resources/orders?per_page=25')->assertOk()->json('table.columns'))->keyBy('attribute');

    expect($columns['placed_at']['component'])->toBe('date-time-field')
        ->and($columns['status']['component'])->toBe('select-field')
        ->and(array_column($columns['status']['options'], 'label', 'value'))->toBe(['pending' => 'Pending', 'paid' => 'Paid', 'shipped' => 'Shipped', 'cancelled' => 'Cancelled'])
        ->and($columns['status']['badges'])->toBe(['pending' => 'warning', 'paid' => 'success', 'shipped' => 'info', 'cancelled' => 'neutral']);
});

it('pages by the allowed sizes', function (string $query, int $rows, int $firstId) {
    signInAs('admin@lathephp.com');

    $json = $this->getJson("/lathe/api/resources/orders?{$query}")->assertOk()->json();

    expect($json['table']['rows'])->toHaveCount($rows)
        ->and($json['table']['rows'][0]['id'])->toBe($firstId);
})->with([
    ['per_page=100', 100, 10000],
    ['per_page=50', 50, 10000],
    ['per_page=101', 25, 10000],
    ['per_page=7', 25, 10000],
    ['page=2', 25, 9975],
    ['page=400', 25, 25],
]);

it('answers 422 for a bad page size or a page past the cap', function (string $query, string $key) {
    signInAs('admin@lathephp.com');

    $this->getJson("/lathe/api/resources/orders?{$query}")->assertUnprocessable()->assertJsonValidationErrors([$key]);
})->with([
    ['per_page=abc', 'per_page'],
    ['page=401', 'page'],
]);

it('says Narrow the filters for a page past the cap', function () {
    signInAs('admin@lathephp.com');

    expect($this->getJson('/lathe/api/resources/orders?page=401')->json('errors.page.0'))->toBe('Narrow the filters');
});

it('counts to the cap without a plus, then caps above it with one limited query', function () {
    signInAs('admin@lathephp.com');
    $this->getJson('/lathe/api/resources/orders/count')->assertOk()->assertExactJson(['value' => 10000, 'exact' => false, 'capped' => false, 'estimated' => false]);

    Order::forceCreate(['customer_id' => 1, 'number' => 'ORD-99999999', 'status' => 'paid', 'total_cents' => 100, 'placed_at' => now()]);
    $queries = queriesDuring(fn () => $this->getJson('/lathe/api/resources/orders/count')->assertExactJson(['value' => 10000, 'exact' => false, 'capped' => true, 'estimated' => false]));

    $counts = array_values(array_filter($queries, fn (string $sql) => str_contains(strtolower($sql), 'count(')));
    expect($counts)->toHaveCount(1)->and($counts[0])->toContain('limit 10001');
});

it('keeps the first paint at four queries and the count at two, at 25 and 100 rows', function (int $perPage) {
    Model::preventLazyLoading();
    $user = User::where('email', 'admin@lathephp.com')->firstOrFail();
    $this->withSession(['login_web_'.sha1(SessionGuard::class) => $user->getKey()]);
    $this->app['auth']->forgetGuards();
    warmNavigationBadges();

    $page = queriesDuring(fn () => $this->get("/lathe/resources/orders?per_page={$perPage}")->assertOk());
    $count = queriesDuring(fn () => $this->get("/lathe/resources/orders?per_page={$perPage}", [
        'X-Inertia' => 'true',
        'X-Inertia-Partial-Component' => 'Resources/Index',
        'X-Inertia-Partial-Data' => 'count',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
    ])->assertOk());

    // The rows, the eager-loaded customers, the user's saved tabs and the user's column choice.
    expect(count($page))->toBeLessThanOrEqual(4)
        ->and(count($count))->toBeLessThanOrEqual(2);
})->with([25, 100]);

it('renders the index page with its props and a deferred count', function () {
    signInAs('admin@lathephp.com');

    $this->get('/lathe/resources/orders')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Resources/Index', shouldExist: false)
            ->has('table.rows', 25)
            ->where('resource.uriKey', 'orders')
            ->missing('count'));
});

it('gives each row the abilities of its user', function (string $email, bool $canChange) {
    signInAs($email);

    $row = $this->getJson('/lathe/api/resources/orders')->json('table.rows.0');

    expect($row['abilities'])->toBe(['view' => true, 'update' => $canChange, 'delete' => $canChange, 'replicate' => $canChange]);
})->with([
    ['admin@lathephp.com', true],
    ['editor@lathephp.com', false],
]);

it('shows a script tag in a customer name as text', function () {
    signInAs('admin@lathephp.com');
    Customer::whereKey(Order::find(10000)?->customer_id)->update(['name' => '<script>alert(1)</script>']);

    $response = $this->getJson('/lathe/api/resources/orders');

    expect($response->json('table.rows.0.cells.customer.display'))->toBe('<script>alert(1)</script>');
});

it('answers 404 for an unknown Resource key', function () {
    signInAs('admin@lathephp.com');

    $this->getJson('/lathe/api/resources/nothing')->assertNotFound();
    $this->getJson('/lathe/api/resources/nothing/count')->assertNotFound();
    $this->get('/lathe/resources/nothing')->assertNotFound();
});

it('answers 403 on the page, the API and the count when viewAny is denied', function () {
    signInAs('admin@lathephp.com');
    Gate::policy(Order::class, EmptyPolicy::class);

    $this->getJson('/lathe/api/resources/orders')->assertForbidden();
    $this->getJson('/lathe/api/resources/orders/count')->assertForbidden();
    $this->get('/lathe/resources/orders')->assertForbidden();
});

it('holds no password or remember token in a User index', function () {
    signInAs('admin@lathephp.com');

    $json = $this->getJson('/lathe/api/resources/users')->assertOk()->getContent();

    expect($json)->not->toContain('password')->not->toContain('remember_token');
});

it('shows a bad parameter as a banner on the index with no rows, never a redirect', function (string $query, string $key, string $message) {
    signInAs('admin@lathephp.com');

    $this->get("/lathe/resources/orders?{$query}")
        ->assertStatus(422)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Resources/Index', false)
            ->where('error', ['key' => $key, 'message' => $message])
            ->where('resource.uriKey', 'orders')
            ->where('table.rows', [])
            ->missing('count'));
})->with([
    ['page=401', 'page', 'Narrow the filters'],
    ['page=abc', 'page', 'The page must be a whole number of 1 or more.'],
    ['sort=password', 'sort', 'sort is invalid'],
]);
