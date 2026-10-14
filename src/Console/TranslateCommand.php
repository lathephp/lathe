<?php

namespace Lathe\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Lathe\Lathe;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'lathe:translate')]
class TranslateCommand extends Command
{
    protected $signature = 'lathe:translate {language? : The language or locale, such as fr or fr-CA} {--force : Replace an existing file}';

    protected $description = 'Copy the English Lathe catalogue to lang/vendor/lathe for translating';

    public function handle(Filesystem $files): int
    {
        $language = $this->stringArgument('language');

        // Symfony would throw for a required argument under --no-interaction, so the argument is optional and checked here.
        if ($language === '') {
            $this->components->error(Lathe::__('Not enough arguments (missing: ":name").', ['name' => 'language']));

            return self::FAILURE;
        }

        $target = lang_path("vendor/lathe/{$language}.json");

        if ($files->exists($target) && ! $this->option('force')) {
            $this->components->error(Lathe::__(':path already exists. Use --force to replace it.', ['path' => $target]));

            return self::FAILURE;
        }

        $files->ensureDirectoryExists(dirname($target));
        $files->copy(__DIR__.'/../../lang/en.json', $target);
        $this->components->info(Lathe::__('Wrote :path.', ['path' => $target]));

        return self::SUCCESS;
    }

    protected function stringArgument(string $name): string
    {
        $value = $this->argument($name);

        return is_string($value) ? $value : '';
    }
}
