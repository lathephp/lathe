<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Lathe\Http\Middleware\HandleInertiaRequests;
use Lathe\Tests\Benchmark\BaselineGate;
use Lathe\Tests\Benchmark\BenchmarkTestCase;
use Lathe\Tests\Benchmark\ResultFile;
use Lathe\Tests\Benchmark\Timing;
use Workbench\App\Models\User;

uses(BenchmarkTestCase::class)->group('budget');

/**
 * Send one Inertia visit through the HTTP kernel in this process. The time covers handle()
 * and reading the body: middleware, queries and serialization, no network.
 *
 * @return array{ms: float, status: int, page: array<string, mixed>}
 */
function inertiaVisit(string $uri): array
{
    $kernel = app(Kernel::class);
    $request = Request::create($uri, 'GET', server: [
        'HTTP_X_INERTIA' => 'true',
        'HTTP_X_INERTIA_VERSION' => (string) app(HandleInertiaRequests::class)->version(request()),
        'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
    ]);

    $started = hrtime(true);
    $response = $kernel->handle($request);
    $body = (string) $response->getContent();
    $ms = Timing::since($started);
    $kernel->terminate($request, $response);

    /** @var array<string, mixed> $page */
    $page = json_decode($body, true) ?? [];

    return ['ms' => $ms, 'status' => $response->getStatusCode(), 'page' => $page];
}

/**
 * The rows an index page answered with.
 *
 * @param  array<string, mixed>  $page
 */
function indexRows(array $page): int
{
    return count((array) data_get($page, 'props.table.rows', []));
}

it('answers the orders list within 50 ms at p95 with the same queries for 10 and 100 rows', function () {
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());

    // One warming scan: the whole table, then the pages of the index the sort reads.
    DB::select('select count(*) from orders');
    DB::select('select count(*) from orders force index (orders_placed_at_index) where placed_at is not null');

    // 50 warm-up visits are dropped, 500 are timed. 80% ask for page 1, the rest for pages 2 to 20,
    // newest first, and every 5th sorts oldest first. The seed keeps the mix the same each run.
    mt_srand(1);
    $samples = [];

    for ($i = 1; $i <= 550; $i++) {
        $page = mt_rand(1, 100) <= 80 ? 1 : mt_rand(2, 20);
        $sort = $i % 5 === 0 ? 'placed_at' : '-placed_at';
        $visit = inertiaVisit("/lathe/resources/orders?sort={$sort}&page={$page}");

        expect($visit['status'])->toBe(200, "page {$page} sort {$sort} answered {$visit['status']}")
            ->and($visit['page']['component'] ?? null)->toBe('Resources/Index')
            ->and(indexRows($visit['page']))->toBe(25);

        if ($i > 50) {
            $samples[] = $visit['ms'];
        }
    }

    $p95 = Timing::p95($samples);

    // Page sizes outside the options fall back to the first one, so 10 is added for this check.
    config(['lathe.pagination.per_page_options' => [10, 25, 50, 100]]);
    Model::preventLazyLoading();
    $ran = 0;
    DB::listen(function () use (&$ran) {
        $ran++;
    });
    $queries = [];

    foreach ([10, 100] as $perPage) {
        $ran = 0;
        $visit = inertiaVisit("/lathe/resources/orders?sort=-placed_at&per_page={$perPage}");
        $queries[$perPage] = $ran;
        expect(indexRows($visit['page']))->toBe($perPage);
    }

    Model::preventLazyLoading(false);

    $metrics = [
        ['name' => 'B1 list p95', 'value' => round($p95, 2), 'unit' => 'ms', 'budget' => 50],
        ['name' => 'B1 list queries', 'value' => $queries[10], 'unit' => 'queries', 'budget' => null],
    ];
    $file = ResultFile::append($metrics);
    fwrite(STDERR, sprintf("\nB1 list p95 %.2f ms (budget 50 ms), %d queries at 10 rows, %d at 100 rows\nresult file: %s\n", $p95, $queries[10], $queries[100], $file));

    expect($p95)->toBeLessThanOrEqual(50.0)
        ->and($queries[10])->toBe($queries[100])
        ->and(BaselineGate::compare($metrics, __DIR__.'/baseline.json'))->toBe([]);
})->skip(fn () => BenchmarkTestCase::benchDatabase() === null, 'Set LATHE_BENCH_DATABASE to a seeded MySQL database to run the timing budgets.');
