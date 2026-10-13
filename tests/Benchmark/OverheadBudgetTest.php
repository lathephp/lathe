<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Lathe\Lathe;
use Lathe\Tests\Benchmark\BaselineGate;
use Lathe\Tests\Benchmark\BenchmarkTestCase;
use Lathe\Tests\Benchmark\ResultFile;
use Lathe\Tests\Benchmark\ScaleFixture;
use Lathe\Tests\Benchmark\Timing;
use Workbench\App\Models\User;

uses(BenchmarkTestCase::class)->group('budget');

/**
 * Milliseconds for one JSON GET through the HTTP kernel in this process.
 */
function timedJsonGet(string $uri): float
{
    $kernel = app(Kernel::class);
    $request = Request::create($uri, 'GET', server: ['HTTP_ACCEPT' => 'application/json']);

    $started = hrtime(true);
    $response = $kernel->handle($request);
    $body = (string) $response->getContent();
    $ms = Timing::since($started);
    $kernel->terminate($request, $response);

    expect($response->getStatusCode())->toBe(200, "{$uri}: {$body}");

    return $ms;
}

it('adds at most 5 ms at p95 with 500 Resources registered', function () {
    $root = ScaleFixture::install();
    $this->artisan('lathe:cache')->assertSuccessful();
    Lathe::resources()->flush();
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());

    // The two routes run the same indexed count; they alternate so drift hits both alike.
    $panel = [];
    $plain = [];

    for ($i = 1; $i <= 550; $i++) {
        $panelMs = timedJsonGet('/lathe/api/bench/count');
        $plainMs = timedJsonGet('/bench/plain');

        if ($i > 50) {
            $panel[] = $panelMs;
            $plain[] = $plainMs;
        }
    }

    $overhead = Timing::p95($panel) - Timing::p95($plain);
    $metrics = [
        ['name' => 'B3 overhead p95', 'value' => round($overhead, 2), 'unit' => 'ms', 'budget' => 5],
        ['name' => 'B3 panel p95', 'value' => round(Timing::p95($panel), 2), 'unit' => 'ms', 'budget' => null],
        ['name' => 'B3 plain p95', 'value' => round(Timing::p95($plain), 2), 'unit' => 'ms', 'budget' => null],
    ];
    $file = ResultFile::append($metrics);
    fwrite(STDERR, sprintf("\nB3 overhead p95 %.2f ms (panel %.2f, plain %.2f; budget 5 ms)\nresult file: %s\n", $overhead, Timing::p95($panel), Timing::p95($plain), $file));

    Lathe::resources()->clearCache();
    File::deleteDirectory($root);

    expect($overhead)->toBeLessThanOrEqual(5.0)
        ->and(BaselineGate::compare($metrics, __DIR__.'/baseline.json'))->toBe([]);
})->skip(fn () => BenchmarkTestCase::benchDatabase() === null, 'Set LATHE_BENCH_DATABASE to a seeded MySQL database to run the timing budgets.');
