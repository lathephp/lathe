<?php

namespace Lathe\Tests\Benchmark;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

/**
 * The JSON file of one timing run: a header naming the machine and every version,
 * then the metrics each budget test appends.
 */
class ResultFile
{
    /**
     * The run id when LATHE_BENCH_RUN names none, fixed once so every test of this process shares a file.
     */
    protected static ?string $run = null;

    public static function path(): string
    {
        $folder = getenv('LATHE_BENCH_RESULTS') ?: sys_get_temp_dir().'/lathe-bench';
        $run = getenv('LATHE_BENCH_RUN') ?: static::$run ??= date('Ymd-His').'-'.getmypid();

        return "{$folder}/{$run}.json";
    }

    /**
     * Add the metrics to this run's file, replacing any of the same name, and return its path.
     *
     * @param  list<array{name: string, value: float|int, unit: string, budget: float|int|null}>  $metrics
     */
    public static function append(array $metrics): string
    {
        $path = static::path();

        /** @var array{header: array<string, mixed>, metrics: list<array{name: string, value: float|int, unit: string, budget: float|int|null}>} $run */
        $run = is_file($path)
            ? json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR)
            : ['header' => static::header(), 'metrics' => []];

        $names = array_column($metrics, 'name');
        $kept = array_filter($run['metrics'], fn (array $metric) => ! in_array($metric['name'], $names, true));
        $run['metrics'] = [...array_values($kept), ...$metrics];

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), recursive: true);
        }

        file_put_contents($path, json_encode($run, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    public static function header(): array
    {
        $cpuinfo = (string) @file_get_contents('/proc/cpuinfo');
        $meminfo = (string) @file_get_contents('/proc/meminfo');
        $load = explode(' ', (string) @file_get_contents('/proc/loadavg'));
        $memory = static::command(['podman', 'inspect', 'lathe-mysql84', '--format', '{{.HostConfig.Memory}}']);
        $driver = DB::connection()->getDriverName();
        $mysql = in_array($driver, ['mysql', 'mariadb'], true);
        $pool = $mysql ? DB::scalar('select @@innodb_buffer_pool_size') : null;

        return [
            'date' => date(DATE_ATOM),
            'cpu_model' => static::cpuModel($cpuinfo),
            'cores' => preg_match_all('/^processor\s*:/m', $cpuinfo),
            'memory_available_kb' => preg_match('/^MemAvailable:\s+(\d+)/m', $meminfo, $match) === 1 ? (int) $match[1] : null,
            'load_average' => array_map('floatval', array_slice($load, 0, 3)),
            'kernel' => php_uname('r'),
            'mysql_memory_limit' => $memory === null ? null : (int) $memory,
            'innodb_buffer_pool_size' => is_numeric($pool) ? (int) $pool : null,
            'versions' => [
                'php' => PHP_VERSION,
                'database' => $driver,
                'mysql' => $mysql ? DB::scalar('select version()') : null,
                'node' => static::command(['node', '--version']),
                'chromium' => static::chromium(),
                'lathe' => static::command(['git', '-C', dirname(__DIR__, 2), 'rev-parse', '--short', 'HEAD']),
            ],
        ];
    }

    /**
     * The model name x86 kernels print; ARM kernels print only the implementer and part codes.
     */
    protected static function cpuModel(string $cpuinfo): ?string
    {
        if (preg_match('/^model name\s*:\s*(.+)$/m', $cpuinfo, $match) === 1) {
            return trim($match[1]);
        }

        if (preg_match('/^CPU implementer\s*:\s*(\S+)$.*?^CPU part\s*:\s*(\S+)$/ms', $cpuinfo, $match) === 1) {
            return "implementer {$match[1]} part {$match[2]}";
        }

        return null;
    }

    /**
     * The Chromium the browser budgets run: Playwright's newest download, else the system one.
     */
    protected static function chromium(): ?string
    {
        $bundled = glob((getenv('HOME') ?: '~').'/.cache/ms-playwright/chromium-*/chrome-linux*/chrome') ?: [];
        natsort($bundled);

        foreach ([...array_reverse($bundled), 'chromium-browser', 'chromium'] as $binary) {
            if (($version = static::command([$binary, '--version'])) !== null) {
                return $version;
            }
        }

        return null;
    }

    /**
     * The trimmed output of a command, or null when it is missing or fails.
     *
     * @param  list<string>  $command
     */
    protected static function command(array $command): ?string
    {
        try {
            $result = Process::timeout(10)->run($command);
        } catch (\Throwable) {
            return null;
        }

        return $result->successful() && trim($result->output()) !== '' ? trim($result->output()) : null;
    }
}
