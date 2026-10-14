<?php

namespace Lathe\Console;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'lathe:resource-plugin')]
class MakeResourcePluginCommand extends GeneratorCommand
{
    protected $signature = 'lathe:resource-plugin {name? : The class name} {--plugin= : Write into a plugin, as vendor/name} {--force : Overwrite an existing class}';

    protected $description = 'Create a Lathe resource plugin and its React component';

    protected function stub(): string
    {
        return 'resource-plugin';
    }

    protected function folder(): string
    {
        return 'Plugins';
    }

    /**
     * A plugin ships its own built frontend, so only an in-app class gets a component here.
     */
    protected function scaffoldReact(Filesystem $files, string $class): void
    {
        if (! $this->option('plugin')) {
            $this->writeComponent($files, 'resource-plugin-react', 'plugins', $class, ['component' => Str::kebab($class)]);
        }
    }
}
