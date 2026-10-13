<?php

namespace Lathe\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
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
        {--api= : The snapshot folder (default: the package api)}';

    protected $description = 'Check the public API against its snapshot';

    public function handle(ApiSnapshot $snapshot, Filesystem $files): int
    {
        $root = dirname(__DIR__, 2);
        $src = $this->stringOption('src') ?? "{$root}/src";
        $api = $this->stringOption('api') ?? "{$root}/api";
        $current = $snapshot->php($src);

        if ($this->option('write')) {
            $files->ensureDirectoryExists($api);
            $files->put("{$api}/php.txt", implode(PHP_EOL, $current).PHP_EOL);
            $this->components->info('Wrote '.count($current).' symbols to api/php.txt.');

            return self::SUCCESS;
        }

        $listed = $files->exists("{$api}/php.txt")
            ? array_values(array_filter(array_map('trim', explode("\n", $files->get("{$api}/php.txt")))))
            : [];

        $problems = $this->compare($listed, $current);

        if ($problems === []) {
            $this->components->info('The public API matches api/php.txt ('.count($current).' symbols).');

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
