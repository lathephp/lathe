<?php

namespace Lathe\Console;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'lathe:page')]
class MakePageCommand extends GeneratorCommand
{
    protected $signature = 'lathe:page {name? : The class name} {--force : Overwrite an existing class}';

    protected $description = 'Create a Lathe page and its React component';

    protected function stub(): string
    {
        return 'page';
    }

    protected function folder(): string
    {
        return 'Pages';
    }

    protected function scaffoldReact(Filesystem $files, string $class): void
    {
        $this->writeComponent($files, 'page-react', 'pages', $class, ['component' => Str::kebab($class)]);
    }
}
