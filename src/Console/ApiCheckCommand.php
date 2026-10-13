<?php

namespace Lathe\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Lathe\Support\ApiSnapshot;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Compares the package's public API with the snapshot in api/.
 *
 * @internal
 */
#[AsCommand(name: 'lathe:api-check')]
class ApiCheckCommand extends Command
{
    protected $signature = 'lathe:api-check
        {--write : Rewrite the snapshot from the current source}
        {--src= : The PHP source folder (default: the package src)}
        {--api= : The snapshot folder (default: the package api)}
        {--js-root= : The folder holding packages/core and packages/ui (default: the package root)}';

    protected $description = 'Check the public API against its snapshot';

    public function handle(ApiSnapshot $snapshot, Filesystem $files): int
    {
        $root = dirname(__DIR__, 2);
        $src = $this->stringOption('src') ?? "{$root}/src";
        $api = $this->stringOption('api') ?? "{$root}/api";
        $snapshots = ['php.txt' => $snapshot->php($src)];
        $jsRoot = $this->stringOption('js-root') ?? ($this->stringOption('src') === null ? $root : null);

        if ($jsRoot !== null) {
            $js = $this->jsExports($root, $jsRoot);

            if ($js === null) {
                return self::FAILURE;
            }

            $snapshots['js.txt'] = $js;
        }

        if ($this->option('write')) {
            $files->ensureDirectoryExists($api);

            foreach ($snapshots as $file => $lines) {
                $files->put("{$api}/{$file}", implode(PHP_EOL, $lines).PHP_EOL);
                $this->components->info('Wrote '.count($lines)." symbols to api/{$file}.");
            }

            return self::SUCCESS;
        }

        $problems = [];

        foreach ($snapshots as $file => $lines) {
            $listed = $files->exists("{$api}/{$file}")
                ? array_values(array_filter(array_map('trim', explode("\n", $files->get("{$api}/{$file}")))))
                : [];

            $problems = [...$problems, ...array_map(fn (string $problem) => "{$file}: {$problem}", $this->compare($listed, $lines))];
        }

        if ($problems === []) {
            $this->components->info('The public API matches its snapshots ('.implode(', ', array_map(fn (string $file, array $lines) => count($lines)." in {$file}", array_keys($snapshots), $snapshots)).').');

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $this->line($problem);
        }

        $this->components->error(count($problems).' API differences. Tag new symbols @internal, or run lathe:api-check --write and add an [api] CHANGELOG entry.');

        return self::FAILURE;
    }

    /**
     * @param  list<string>  $listed
     * @param  list<string>  $current
     * @return list<string>
     */
    protected function compare(array $listed, array $current): array
    {
        $added = array_values(array_diff($current, $listed));
        $gone = array_values(array_diff($listed, $current));
        $problems = [];

        foreach ($added as $line) {
            $changed = array_filter($gone, fn (string $old) => $this->name($old) === $this->name($line));

            if ($changed !== []) {
                $problems[] = 'changed: '.reset($changed).' -> '.$line;
                $gone = array_values(array_diff($gone, $changed));
            } else {
                $problems[] = 'not in the snapshot: '.$line;
            }
        }

        foreach ($gone as $line) {
            $problems[] = 'gone: '.$line;
        }

        return $problems;
    }

    /**
     * The exports of @lathephp/core and @lathephp/ui, listed by scripts/js-api.mjs.
     *
     * @return list<string>|null
     */
    protected function jsExports(string $root, string $jsRoot): ?array
    {
        $result = Process::path($root)->run(['bash', 'scripts/pnpm.sh', 'exec', 'node', 'scripts/js-api.mjs', $jsRoot]);

        if (! $result->successful()) {
            $this->components->error('Could not list the JavaScript exports: '.trim($result->errorOutput()));

            return null;
        }

        // pnpm may print install notes first in a fresh checkout; every export line names a package.
        return array_values(array_filter(
            array_map('trim', explode("\n", $result->output())),
            fn (string $line) => str_starts_with($line, '@lathephp/'),
        ));
    }

    protected function name(string $line): string
    {
        return (string) preg_replace('/\(.*$/', '', $line);
    }

    protected function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
