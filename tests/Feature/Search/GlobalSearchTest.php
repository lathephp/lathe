<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Search\GlobalSearchResult;
use Lathe\Search\SearchProvider;
use Lathe\Tests\Fixtures\Models\Gadget;
use Lathe\Tests\Fixtures\Policies\EmptyPolicy;
use Lathe\Tests\Fixtures\Resources\JoinedCustomer;
use Lathe\Tests\Fixtures\Resources\SingleResultCustomer;
use Workbench\App\Lathe\Resources\Customer as CustomerResource;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

/**
 * One of 25 Resources over customers, keyed and labelled by the number in its class name.
 */
abstract class GlobalSearchBulk extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Customer::class)->canonical(false)->title('name')
            ->searchable(['name'])->globallySearchable()->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }

    public static function uriKey(): string
    {
        return 'bulk-'.substr(static::class, -2);
    }

    public static function label(): string
    {
        return 'Bulk '.substr(static::class, -2);
    }
}

class GlobalSearchBulk01 extends GlobalSearchBulk {}
class GlobalSearchBulk02 extends GlobalSearchBulk {}
class GlobalSearchBulk03 extends GlobalSearchBulk {}
class GlobalSearchBulk04 extends GlobalSearchBulk {}
class GlobalSearchBulk05 extends GlobalSearchBulk {}
class GlobalSearchBulk06 extends GlobalSearchBulk {}
class GlobalSearchBulk07 extends GlobalSearchBulk {}
class GlobalSearchBulk08 extends GlobalSearchBulk {}
class GlobalSearchBulk09 extends GlobalSearchBulk {}
class GlobalSearchBulk10 extends GlobalSearchBulk {}
class GlobalSearchBulk11 extends GlobalSearchBulk {}
class GlobalSearchBulk12 extends GlobalSearchBulk {}
class GlobalSearchBulk13 extends GlobalSearchBulk {}
class GlobalSearchBulk14 extends GlobalSearchBulk {}
class GlobalSearchBulk15 extends GlobalSearchBulk {}
class GlobalSearchBulk16 extends GlobalSearchBulk {}
class GlobalSearchBulk17 extends GlobalSearchBulk {}
class GlobalSearchBulk18 extends GlobalSearchBulk {}
class GlobalSearchBulk19 extends GlobalSearchBulk {}
class GlobalSearchBulk20 extends GlobalSearchBulk {}
class GlobalSearchBulk21 extends GlobalSearchBulk {}
class GlobalSearchBulk22 extends GlobalSearchBulk {}
class GlobalSearchBulk23 extends GlobalSearchBulk {}
class GlobalSearchBulk24 extends GlobalSearchBulk {}
class GlobalSearchBulk25 extends GlobalSearchBulk {}

class GlobalSearchGadget extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Gadget::class)->title('name')->searchable(['name'])->globallySearchable()->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }
}

class GlobalSearchJoinedCustomer extends JoinedCustomer
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->globallySearchable();
    }
}

class GlobalSearchHiddenCustomer extends CustomerResource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canonical(false)->canSee(fn () => false);
    }
}

class GlobalSearchBrokenCustomer extends CustomerResource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canonical(false);
    }

    public static function searchQuery(LatheRequest $request, Builder $query): Builder
    {
        throw new RuntimeException('The search index is down.');
    }
}

class GlobalSearchHelpProvider extends SearchProvider
{
    public static bool $allowed = true;

    public function title(): string
    {
        return 'Help';
    }

    public function uriKey(): string
    {
        return 'help';
    }

    public function authorize(LatheRequest $request): bool
    {
        return static::$allowed;
    }

    public function search(LatheRequest $request): array
    {
        $results = [GlobalSearchResult::make('Refunds')->subtitle('Docs')->url('/plugins/help/refunds')->id('refunds')];

        foreach (range(2, 7) as $i) {
            $results[] = GlobalSearchResult::make("Article {$i}");
        }

        return $results;
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => $user->email === 'admin@lathephp.com');
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    GlobalSearchHelpProvider::$allowed = true;
});

/**
 * The groups of one global search, keyed by resourceName.
 *
 * @return array<string, array{resourceName: string, label: string, results: list<array<string, mixed>>}>
 */
function globalSearchGroups(string $term): array
{
    $groups = test()->getJson('/lathe/api/search?q='.urlencode($term))->assertOk()->json('groups');

    return array_column($groups, null, 'resourceName');
}

it('returns the Ann customers as rows with exactly the eight keys', function () {
    Customer::whereKey(12)->update(['name' => 'Annie Twelve', 'email' => 'annie@example.com']);

    $response = $this->getJson('/lathe/api/search?q=ann')->assertOk();
    $customers = $response->json('groups.0');

    expect($customers['resourceName'])->toBe('customers')
        ->and($customers['label'])->toBe('Customers')
        ->and($customers['results'])->toContain([
            'resourceTitle' => 'Customers',
            'title' => 'Annie Twelve',
            'subTitle' => 'annie@example.com',
            'resourceName' => 'customers',
            'resourceId' => 12,
            'avatar' => null,
            'rounded' => false,
            'url' => '/resources/customers/12',
        ])
        ->and(array_column($customers['results'], 'title'))->toContain('Ann Taylor', 'Anna Kowalski', 'Annette Fox')
        ->and($response->json('skipped'))->toBe([]);

    foreach ($customers['results'] as $row) {
        expect(array_keys($row))->toBe(['resourceTitle', 'title', 'subTitle', 'resourceName', 'resourceId', 'avatar', 'rounded', 'url'])
            ->and(str_starts_with($row['title'], 'Ann') || str_contains($row['subTitle'], 'ann'))->toBeTrue();
    }
});

it('finds an order by its number in the Orders group', function () {
    $number = Order::findOrFail(12)->number;

    expect(globalSearchGroups($number)['orders']['results'])->toBe([[
        'resourceTitle' => 'Orders',
        'title' => $number,
        'subTitle' => null,
        'resourceName' => 'orders',
        'resourceId' => 12,
        'avatar' => null,
        'rounded' => false,
        'url' => '/resources/orders/12',
    ]]);
});

it('caps each group at 5 rows, or at global_search.results', function () {
    foreach (range(1, 4) as $i) {
        Customer::forceCreate(['name' => "Annabel {$i}", 'email' => "annabel{$i}@example.com"]);
    }

    expect(globalSearchGroups('ann')['customers']['results'])->toHaveCount(5);

    config(['lathe.global_search.results' => 2]);

    expect(globalSearchGroups('ann')['customers']['results'])->toHaveCount(2);
});

it('caps a Resource\'s group at its globalSearchResults()', function () {
    Lathe::registerResources(SingleResultCustomer::class);

    $groups = globalSearchGroups('ann');

    expect($groups['single-result-customers']['results'])->toHaveCount(1)
        ->and($groups['customers']['results'])->toHaveCount(5);
});

it('stops at 100 rows and skips the later Resources', function () {
    foreach (range(1, 6) as $i) {
        Customer::forceCreate(['name' => "Bulkrow {$i}", 'email' => "bulkrow{$i}@example.com"]);
    }
    Lathe::registerResources(...array_map(fn (int $i) => sprintf('GlobalSearchBulk%02d', $i), range(1, 25)));

    $response = $this->getJson('/lathe/api/search?q=bulkrow')->assertOk();
    $rows = array_merge(...array_column($response->json('groups'), 'results'));

    expect($rows)->toHaveCount(100)
        ->and(array_column($response->json('groups'), 'resourceName'))->toBe(array_map(fn (int $i) => sprintf('bulk-%02d', $i), range(1, 20)))
        ->and($response->json('skipped'))->toContain('Bulk 21', 'Bulk 25')
        ->and($response->json('skipped'))->not->toContain('Bulk 20');

    config(['lathe.global_search.results' => 6]);
    $groups = $this->getJson('/lathe/api/search?q=bulkrow')->assertOk()->json('groups');

    expect(array_merge(...array_column($groups, 'results')))->toHaveCount(100)
        ->and($groups)->toHaveCount(17)
        ->and($groups[16]['results'])->toHaveCount(4);
});

it('returns no groups for an empty term', function (string $query) {
    $this->getJson('/lathe/api/search'.$query)->assertOk()->assertExactJson(['groups' => [], 'skipped' => []]);
})->with(['no q' => '', 'empty q' => '?q=', 'blank q' => '?q=%20%20']);

it('answers 422 above the term limits', function (string $term) {
    $this->getJson('/lathe/api/search?q='.urlencode($term))->assertStatus(422)->assertJsonValidationErrors('q');
})->with(['101 chars' => str_repeat('a', 101), '9 words' => 'a b c d e f g h i']);

test('global_search_applies_the_resource_scope', function () {
    Schema::create('gadgets', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->unsignedInteger('team_id');
        $table->foreignId('invoice_id')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
    Gadget::withoutGlobalScopes()->insert([
        ['name' => 'Sprocket ours', 'team_id' => 1],
        ['name' => 'Sprocket theirs', 'team_id' => 2],
    ]);
    Lathe::registerResources(GlobalSearchGadget::class);

    expect(array_column(globalSearchGroups('sprocket')['global-search-gadgets']['results'], 'title'))->toBe(['Sprocket ours']);
})->group('regression');

test('global_search_has_no_ambiguous_id_on_joined_query', function () {
    Schema::create('customer_notes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('customer_id');
        $table->string('name');
        $table->string('status');
    });
    DB::table('customer_notes')->insert([
        ['customer_id' => 2, 'name' => 'Annual review', 'status' => 'open'],
        ['customer_id' => 5, 'name' => 'Call back', 'status' => 'open'],
    ]);
    Lathe::registerResources(GlobalSearchJoinedCustomer::class);

    $response = $this->getJson('/lathe/api/search?q=ann')->assertOk();
    $joined = array_column($response->json('groups'), null, 'resourceName')['global-search-joined-customers'];

    expect($response->json('skipped'))->toBe([])
        ->and(array_column($joined['results'], 'resourceId'))->toBe([2])
        ->and($joined['results'][0]['title'])->toBe('Anna Kowalski');
})->group('regression');

it('returns no group for a Resource that fails canSee', function () {
    Lathe::registerResources(GlobalSearchHiddenCustomer::class);

    // Orders match "ann" through their customer's name.
    expect(array_keys(globalSearchGroups('ann')))->toBe(['customers', 'orders']);
});

it('returns no group for a Resource the user may not viewAny', function () {
    Gate::policy(Order::class, EmptyPolicy::class);

    expect(array_keys(globalSearchGroups('ann')))->toBe(['customers']);
});

it('skips a Resource whose query throws, lists its label and returns the others', function () {
    Lathe::registerResources(GlobalSearchBrokenCustomer::class);

    $response = $this->getJson('/lathe/api/search?q=ann')->assertOk();

    expect($response->json('skipped'))->toBe(['Global Search Broken Customers'])
        ->and(array_column($response->json('groups'), 'resourceName'))->toBe(['customers', 'orders']);
});

it('answers 403 without the globalSearch ability', function () {
    Gate::define('globalSearch', fn () => false);

    $this->getJson('/lathe/api/search?q=ann')->assertForbidden();
});

it('answers 403 when the app defines no globalSearch ability', function () {
    forgetGate('globalSearch');

    $this->getJson('/lathe/api/search?q=ann')->assertForbidden();
});

it('answers 404 when global search is off', function () {
    $this->getJson('/lathe/api/search?q=ann')->assertOk();

    config(['lathe.global_search.enabled' => false]);

    $this->getJson('/lathe/api/search?q=ann')->assertNotFound();
});

it('selects only the key, title and subtitle columns for plain attribute titles', function () {
    $queries = [];
    DB::listen(function (QueryExecuted $event) use (&$queries) {
        $queries[] = $event->sql;
    });

    $this->getJson('/lathe/api/search?q=ann')->assertOk();

    // The Orders search reads customers too, but only inside its own exists subquery.
    $customers = array_values(array_filter($queries, fn (string $sql) => str_starts_with($sql, quotedSql('select "customers".'))));
    expect($customers)->toHaveCount(1)
        ->and($customers[0])->toStartWith(quotedSql('select "customers"."id", "customers"."name", "customers"."email" from "customers"'));
});

it('lists a provider\'s rows under its title, capped, and none when it is not authorized', function () {
    Lathe::registerSearchProviders(new GlobalSearchHelpProvider);

    $help = globalSearchGroups('ann')['help'];

    expect($help['label'])->toBe('Help')
        ->and($help['results'])->toHaveCount(5)
        ->and($help['results'][0])->toBe([
            'resourceTitle' => 'Help',
            'title' => 'Refunds',
            'subTitle' => 'Docs',
            'resourceName' => 'help',
            'resourceId' => 'refunds',
            'avatar' => null,
            'rounded' => false,
            'url' => '/plugins/help/refunds',
        ]);

    GlobalSearchHelpProvider::$allowed = false;

    expect(globalSearchGroups('ann'))->not->toHaveKey('help');
});
