<?php

namespace Lathe\Console;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'lathe:widget')]
class MakeWidgetCommand extends GeneratorCommand
{
    /** @var list<string> */
    protected const TYPES = ['value', 'trend', 'partition', 'progress', 'table', 'custom', 'bar', 'pie', 'radar', 'radial', 'help'];

    protected $signature = 'lathe:widget {name? : The class name} {--type=value : value, trend, partition, progress, table, custom, bar, pie, radar, radial or help} {--force : Overwrite an existing class}';

    protected $description = 'Create a Lathe widget';

    public function handle(Filesystem $files): int
    {
        $type = $this->option('type');

        if (! is_string($type) || ! in_array($type, self::TYPES, true)) {
            $this->components->error('The widget type must be one of: '.implode(', ', self::TYPES).'.');

            return self::FAILURE;
        }

        return parent::handle($files);
    }

    protected function stub(): string
    {
        return 'widget-'.$this->option('type');
    }

    protected function folder(): string
    {
        return 'Widgets';
    }

    protected function replacements(string $class): array
    {
        return ['component' => Str::kebab($class)];
    }

    protected function scaffoldReact(Filesystem $files, string $class): void
    {
        if ($this->option('type') === 'custom') {
            $this->writeComponent($files, 'widget-react', 'widgets', $class, ['component' => Str::kebab($class)]);
        }
    }
}
