<?php

namespace Lathe\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Lathe\Assets\AssetRegistry;
use Lathe\Lathe;

class AssetsCommand extends Command
{
    protected $signature = 'lathe:assets {--force : Overwrite the copies that already exist}';

    protected $description = 'Copy the registered plugin scripts and styles to the public folder';

    public function handle(AssetRegistry $assets): int
    {
        // Assets that a serving() hook registers count too.
        Lathe::ensureBooted();

        foreach (['scripts' => $assets->scripts(), 'styles' => $assets->styles()] as $type => $entries) {
            foreach ($entries as $name => $source) {
                if ($assets->isRemote($source)) {
                    continue;
                }

                $this->copy($assets->copyPath($type, (string) $name), (string) $name, $source);
            }
        }

        return self::SUCCESS;
    }

    protected function copy(string $target, string $name, string $source): void
    {
        if (! is_file($source)) {
            $this->components->warn("Skipped {$name}: the source file {$source} does not exist.");

            return;
        }

        if (is_file($target) && ! $this->option('force')) {
            $this->components->twoColumnDetail($name, 'kept, use --force to overwrite');

            return;
        }

        File::ensureDirectoryExists(dirname($target));
        File::copy($source, $target);
        $this->components->twoColumnDetail($name, 'copied');
    }
}
