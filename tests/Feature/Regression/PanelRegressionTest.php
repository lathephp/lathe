<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Lathe\Events\ServingLathe;
use Lathe\Exceptions\ResourceModelCollisionException;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\ResourceDefinition;
use Lathe\Tests\Fixtures\Models\Invoice;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

afterEach(fn () => Lathe::resources()->clearCache());

/**
 * @return list<string>
 */
function sqlDuring(Closure $callback): array
{
    $queries = [];
    DB::listen(function ($event) use (&$queries) {
        $queries[] = $event->sql;
    });
    $callback();

    return $queries;
}

test('userless_request_is_denied_not_unscoped', function () {
    $queries = sqlDuring(fn () => test()->getJson('/lathe/api/resources/orders')->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']));

    expect($queries)->toBe([]);
})->group('regression');

test('boot_request_never_leaks_into_first_request', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    $this->artisan('lathe:cache')->assertSuccessful();

    expect(app()->bound(LatheRequest::class))->toBeFalse();

    $seen = [];
    Event::listen(ServingLathe::class, function () use (&$seen) {
        $request = app(LatheRequest::class);
        $seen[] = [$request->path(), $request->resourceKey()];
    });
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail())
        ->getJson('/lathe/api/resources/orders')->assertOk();

    expect($seen)->toBe([['lathe/api/resources/orders', null]])
        ->and(app()->bound(LatheRequest::class))->toBeFalse();

    Lathe::resources()->clearCache();
})->group('regression');

test('resource_without_policy_denies_unless_allow_all', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::guessPolicyNamesUsing(fn () => []);
    $admin = User::where('email', 'admin@lathephp.com')->firstOrFail();

    $this->actingAs($admin)->getJson('/lathe/api/resources/orders')->assertForbidden();

    Lathe::resources()->reset();
    $open = new class extends Workbench\App\Lathe\Resources\Order
    {
        public static function uriKey(): string
        {
            return 'open-orders';
        }

        protected function configure(ResourceDefinition $definition): ResourceDefinition
        {
            return parent::configure($definition)->allowAll();
        }
    };
    Lathe::registerResources($open::class);

    $this->actingAs($admin)->getJson('/lathe/api/resources/open-orders')->assertOk();
})->group('regression');

test('unknown_route_returns_404_not_500', function (string $url) {
    $this->artisan('migrate')->assertSuccessful();
    $admin = User::forceCreate(['name' => 'Admin User', 'email' => 'admin@lathephp.com', 'password' => bcrypt('password')]);

    $this->actingAs($admin)->get($url)->assertNotFound();
    $this->actingAs($admin)->getJson($url)->assertNotFound();
})->with(['/lathe/nothing', '/lathe/resources/nothing', '/lathe/api/resources/nothing', '/lathe/plugins/nothing'])->group('regression');

test('two_resources_on_one_model_resolve_deterministically', function () {
    $namespace = 'Twin'.bin2hex(random_bytes(4));
    $directory = sys_get_temp_dir().'/lathe-twin-'.$namespace;
    File::ensureDirectoryExists($directory);
    foreach (['Bills', 'Invoices'] as $class) {
        File::put("{$directory}/{$class}.php", "<?php\nnamespace {$namespace};\nclass {$class} extends \\Lathe\\Resources\\Resource {\n    protected function configure(\\Lathe\\Resources\\ResourceDefinition \$d): \\Lathe\\Resources\\ResourceDefinition { return \$d->model(\\".Invoice::class."::class); }\n    public function fields(\\Lathe\\Http\\Requests\\LatheRequest \$r): array { return []; }\n}\n");
    }
    Lathe::resources()->reset();
    Lathe::discoverResources($directory, $namespace);

    $messages = [];
    foreach ([1, 2] as $run) {
        Lathe::resources()->flush();
        try {
            Lathe::resources()->manifest();
        } catch (ResourceModelCollisionException $e) {
            $messages[] = $e->getMessage();
        }
    }

    expect($messages)->toHaveCount(2)
        ->and($messages[0])->toBe($messages[1])
        ->toBe("Resources {$namespace}\\Bills and {$namespace}\\Invoices are both canonical for ".Invoice::class.'. Call canonical(false) on the extra Resource.');

    File::deleteDirectory($directory);
})->group('regression');

test('index_query_count_is_flat_across_page_sizes', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Model::preventLazyLoading();
    $admin = User::where('email', 'admin@lathephp.com')->firstOrFail();

    $at25 = sqlDuring(fn () => test()->actingAs($admin)->getJson('/lathe/api/resources/orders?per_page=25')->assertOk());
    $at100 = sqlDuring(fn () => test()->actingAs($admin)->getJson('/lathe/api/resources/orders?per_page=100')->assertOk());

    expect(count($at25))->toBe(count($at100))->toBeLessThanOrEqual(3);
})->group('regression');

test('capped_count_never_runs_full_count', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Order::forceCreate(['customer_id' => 1, 'number' => 'ORD-99999999', 'status' => 'paid', 'total_cents' => 100, 'placed_at' => now()]);
    $admin = User::where('email', 'admin@lathephp.com')->firstOrFail();

    $queries = sqlDuring(fn () => test()->actingAs($admin)->getJson('/lathe/api/resources/orders/count')->assertExactJson(['count' => 10000, 'capped' => true]));
    $counts = array_values(array_filter($queries, fn (string $sql) => str_contains(strtolower($sql), 'count(')));

    expect($counts)->toHaveCount(1)
        ->and($counts[0])->toContain('limit 10001');
})->group('regression');
