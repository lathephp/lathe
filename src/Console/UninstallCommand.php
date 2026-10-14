<?php

namespace Lathe\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Lathe\Lathe;

use function Laravel\Prompts\confirm;

class UninstallCommand extends Command
{
    protected $signature = 'lathe:uninstall';

    protected $description = 'Remove the Lathe service provider, published config and copied assets';

    public function handle(Filesystem $files): int
    {
        // Without a terminal nothing is removed: removal needs a person to say yes.
        if (! $this->input->isInteractive() || ! confirm(Lathe::__('Remove the Lathe service provider, config/lathe.php and public/vendor/lathe?'), false)) {
            $this->components->info(Lathe::__('Nothing was removed.'));

            return self::SUCCESS;
        }

        $files->delete(app_path('Providers/LatheServiceProvider.php'));
        $this->removeProviderRegistration($files);
        $files->deleteDirectory(public_path('vendor/lathe'));
        $files->delete(config_path('lathe.php'));

        $this->components->info(Lathe::__('Lathe is uninstalled. Your tables and app/Lathe stay. Now run: composer remove lathe/lathe'));

        return self::SUCCESS;
    }

    protected function removeProviderRegistration(Filesystem $files): void
    {
        $providers = base_path('bootstrap/providers.php');

        if (! $files->exists($providers)) {
            return;
        }

        $class = preg_quote($this->laravel->getNamespace().'Providers\\LatheServiceProvider::class', '/');
        $files->put($providers, (string) preg_replace('/^\s*\\\\?'.$class.',?\R/m', '', $files->get($providers)));
    }
}
