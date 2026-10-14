<?php

namespace Lathe\Console;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'lathe:lens')]
class MakeLensCommand extends GeneratorCommand
{
    protected $signature = 'lathe:lens {name? : The class name} {--plugin= : Write into a plugin, as vendor/name} {--force : Overwrite an existing class}';

    protected $description = 'Create a Lathe lens';

    protected function stub(): string
    {
        return 'lens';
    }

    protected function folder(): string
    {
        return 'Lenses';
    }
}
