<?php

use Illuminate\Support\Facades\DB;
use Lathe\Tests\Benchmark\BaselineGate;
use Lathe\Tests\Benchmark\BenchmarkTestCase;
use Lathe\Tests\Benchmark\ResultFile;
use Lathe\Tests\Benchmark\Timing;
use Workbench\App\Models\User;

uses(BenchmarkTestCase::class)->group('budget');

/**
 * The plan's EXPLAIN rows for one orders query: the key MySQL chose and whether it sorts in a file.
 *
 * @return array{key: string|null, filesort: bool}
 */
function explainOrders(string $where, string $order): array
{
    $rows = DB::select("explain select * from orders {$where} order by {$order} limit 26");
    $extra = implode(' ', array_map(fn (object $row) => (string) ($row->Extra ?? ''), $rows));

    return ['key' => $rows[0]->key ?? null, 'filesort' => str_contains($extra, 'filesort')];
}

it('answers the three Show path requests within 50 ms at p95 on the snapshot, counting capped', function () {
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    $number = (string) DB::table('orders')->where('id', 12)->value('number');
    $requests = [
        'number search' => '/lathe/resources/orders?q='.urlencode($number),
        'filter pending' => '/lathe/resources/orders?filter[status]=pending',
        'filter pending, sort by total' => '/lathe/resources/orders?filter[status]=pending&sort=-total_cents',
    ];
    $metrics = [];

    foreach ($requests as $name => $uri) {
        $samples = [];

        for ($i = 1; $i <= 550; $i++) {
            $visit = inertiaVisit($uri);
            expect($visit['status'])->toBe(200, "{$name} answered {$visit['status']}");

            if ($i > 50) {
                $samples[] = $visit['ms'];
            }
        }

        $metrics[] = ['name' => "B1 show path {$name} p95", 'value' => round(Timing::p95($samples), 2), 'unit' => 'ms', 'budget' => 50];
    }

    // The deferred count of the widest request stays capped: one limited subselect, never COUNT(*) over orders.
    $counts = [];
    DB::listen(function ($event) use (&$counts) {
        if (str_contains(strtolower($event->sql), 'count(')) {
            $counts[] = $event->sql;
        }
    });
    $count = $this->getJson('/lathe/api/resources/orders/count?filter[status]=pending')->json();

    $plans = [
        'placed_at' => explainOrders('', 'placed_at desc'),
        'pending by total' => explainOrders("where status = 'pending'", 'total_cents desc'),
        'total' => explainOrders('', 'total_cents desc'),
    ];
    $prefix = DB::select("explain select * from orders where number like 'ORD-0000%' limit 26");

    $file = ResultFile::append($metrics);
    fwrite(STDERR, "\n".implode("\n", array_map(fn (array $metric) => sprintf('%s %.2f ms (budget 50 ms)', $metric['name'], $metric['value']), $metrics))."\nplans: ".json_encode($plans).' prefix key: '.($prefix[0]->key ?? 'none')."\nresult file: {$file}\n");

    foreach ($metrics as $metric) {
        expect($metric['value'])->toBeLessThanOrEqual(50.0, $metric['name']);
    }

    expect($count)->toMatchArray(['value' => 10000, 'capped' => true])
        ->and($counts)->toHaveCount(1)
        ->and($counts[0])->toContain('limit 10001')
        ->and($plans['placed_at'])->toBe(['key' => 'orders_placed_at_index', 'filesort' => false])
        ->and($plans['pending by total'])->toBe(['key' => 'orders_status_total_cents_index', 'filesort' => false])
        ->and($plans['total']['key'])->toBe('orders_total_cents_index')
        ->and($prefix[0]->key ?? null)->toBe('orders_number_unique')
        ->and(BaselineGate::compare($metrics, __DIR__.'/baseline.json'))->toBe([]);
})->skip(fn () => BenchmarkTestCase::benchDatabase() === null, 'Set LATHE_BENCH_DATABASE to a seeded MySQL database to run the timing budgets.');
