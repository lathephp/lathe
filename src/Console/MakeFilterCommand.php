<?php

namespace Lathe\Console;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'lathe:filter')]
class MakeFilterCommand extends GeneratorCommand
{
    /** @var list<string> */
    protected const TYPES = ['select', 'boolean', 'date', 'range'];

    protected $signature = 'lathe:filter {name? : The class name} {--type=select : select, boolean, date or range} {--custom : Write a filter class with a React component} {--force : Overwrite an existing class}';

    protected $description = 'Create a Lathe filter';

    public function handle(Filesystem $files): int
    {
        $type = $this->option('type');

        if (! $this->option('custom') && (! is_string($type) || ! in_array($type, self::TYPES, true))) {
            $this->components->error('The filter type must be one of: '.implode(', ', self::TYPES).'.');

            return self::FAILURE;
        }

        return parent::handle($files);
    }

    protected function stub(): string
    {
        return $this->option('custom') ? 'filter-custom' : 'filter-'.$this->option('type');
    }

    protected function folder(): string
    {
        return 'Filters';
    }

    protected function replacements(string $class): array
    {
        return ['component' => Str::kebab($class).'-filter'];
    }

    protected function scaffoldReact(Filesystem $files, string $class): void
    {
        if ($this->option('custom')) {
            $this->writeComponent($files, 'filter-react', 'filters', $class, ['component' => Str::kebab($class).'-filter']);
        }
    }
}
