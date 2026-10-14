<?php

namespace Lathe\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'lathe:stubs')]
class StubsCommand extends Command
{
    protected $signature = 'lathe:stubs {--force : Overwrite stubs that are already published}';

    protected $description = 'Publish the generator stubs to stubs/lathe for editing';

    public function handle(Filesystem $files): int
    {
        $target = $this->laravel->basePath('stubs/lathe');
        $files->ensureDirectoryExists($target);

        $published = 0;

        foreach ($files->files(__DIR__.'/../../stubs') as $file) {
            $path = "{$target}/{$file->getFilename()}";

            if ($files->exists($path) && ! $this->option('force')) {
                continue;
            }

            $files->copy($file->getPathname(), $path);
            $published++;
        }

        $this->components->info("Published {$published} stub(s) to stubs/lathe.");

        return self::SUCCESS;
    }
}
