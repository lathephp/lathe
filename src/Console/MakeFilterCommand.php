<?php

namespace Lathe\Console;

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'lathe:filter')]
class MakeFilterCommand extends GeneratorCommand
{
    /** @var list<string> */
    protected const TYPES = ['select', 'boolean', 'date', 'range'];

    protected $signature = 'lathe:filter {name? : The class name} {--type=select : select, boolean, date or range} {--force : Overwrite an existing class}';

    protected $description = 'Create a Lathe filter';

    public function handle(Filesystem $files): int
    {
        $type = $this->option('type');

        if (! is_string($type) || ! in_array($type, self::TYPES, true)) {
            $this->components->error('The filter type must be one of: '.implode(', ', self::TYPES).'.');

            return self::FAILURE;
        }

        return parent::handle($files);
    }

    protected function stub(): string
    {
        return 'filter-'.$this->option('type');
    }

    protected function folder(): string
    {
        return 'Filters';
    }
}
