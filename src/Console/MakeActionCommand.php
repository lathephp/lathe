<?php

namespace Lathe\Console;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'lathe:action')]
class MakeActionCommand extends GeneratorCommand
{
    protected $signature = 'lathe:action {name? : The class name} {--plugin= : Write into a plugin, as vendor/name} {--force : Overwrite an existing class}';

    protected $description = 'Create a Lathe action';

    protected function stub(): string
    {
        return 'action';
    }

    protected function folder(): string
    {
        return 'Actions';
    }
}
