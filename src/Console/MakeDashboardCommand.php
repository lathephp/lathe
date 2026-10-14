<?php

namespace Lathe\Console;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'lathe:dashboard')]
class MakeDashboardCommand extends GeneratorCommand
{
    protected $signature = 'lathe:dashboard {name? : The class name} {--plugin= : Write into a plugin, as vendor/name} {--force : Overwrite an existing class}';

    protected $description = 'Create a Lathe dashboard';

    protected function stub(): string
    {
        return 'dashboard';
    }

    protected function folder(): string
    {
        return 'Dashboards';
    }
}
