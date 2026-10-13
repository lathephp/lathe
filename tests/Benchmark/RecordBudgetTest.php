<?php

use Illuminate\Support\Facades\DB;
use Lathe\Tests\Benchmark\BenchmarkTestCase;
use Lathe\Tests\Benchmark\ResultFile;
use Lathe\Tests\Benchmark\Timing;
use Workbench\App\Models\User;

uses(BenchmarkTestCase::class)->group('budget');

// inertiaVisit() lives in ListBudgetTest.php, which Pest loads with this file.
it('answers the detail and edit pages of random orders with 200 and reports their p95', function () {
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    $last = (int) DB::table('orders')->max('id');
    $metrics = [];
    $lines = [];

    foreach (['detail' => '', 'edit' => '/edit'] as $name => $suffix) {
        // The same seed each run, so both pages see the same ids; 50 warm-up visits are dropped, 500 are timed.
        mt_srand(2);
        $samples = [];

        for ($i = 1; $i <= 550; $i++) {
            $id = mt_rand(1, $last);
            $visit = inertiaVisit("/lathe/resources/orders/{$id}{$suffix}");

            expect($visit['status'])->toBe(200, "order {$id} {$name} answered {$visit['status']}")
                ->and($visit['page']['component'] ?? null)->toBe($name === 'detail' ? 'Resources/Detail' : 'Resources/Form');

            if ($i > 50) {
                $samples[] = $visit['ms'];
            }
        }

        $p95 = Timing::p95($samples);
        $metrics[] = ['name' => "B2 {$name} p95", 'value' => round($p95, 2), 'unit' => 'ms', 'budget' => 30];
        $lines[] = sprintf('B2 %s p95 %.2f ms (budget 30 ms, calibration pending)', $name, $p95);
    }

    // The 30 ms budget is accepted on the calibration run, so this run reports and never says PASS.
    $file = ResultFile::append($metrics);
    fwrite(STDERR, "\n".implode("\n", $lines)."\nresult file: {$file}\n");
})->skip(fn () => BenchmarkTestCase::benchDatabase() === null, 'Set LATHE_BENCH_DATABASE to a seeded MySQL database to run the timing budgets.');
