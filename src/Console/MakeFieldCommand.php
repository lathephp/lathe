<?php

namespace Lathe\Console;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Lathe\Field\BadgeField;
use Lathe\Field\BooleanField;
use Lathe\Field\CodeField;
use Lathe\Field\DateField;
use Lathe\Field\DateTimeField;
use Lathe\Field\Field;
use Lathe\Field\FileField;
use Lathe\Field\ImageField;
use Lathe\Field\NumberField;
use Lathe\Field\StatusField;
use Lathe\Field\TextareaField;
use Lathe\Field\TextField;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'lathe:field')]
class MakeFieldCommand extends GeneratorCommand
{
    /** @var array<string, class-string> */
    protected const array BASES = [
        'field' => Field::class,
        'text' => TextField::class,
        'textarea' => TextareaField::class,
        'number' => NumberField::class,
        'boolean' => BooleanField::class,
        'date' => DateField::class,
        'datetime' => DateTimeField::class,
        'code' => CodeField::class,
        'badge' => BadgeField::class,
        'status' => StatusField::class,
        'file' => FileField::class,
        'image' => ImageField::class,
    ];

    protected $signature = 'lathe:field {name? : The class name} {--extends=field : The field type to extend} {--custom : Also write a React component} {--force : Overwrite an existing class}';

    protected $description = 'Create a Lathe field';

    public function handle(Filesystem $files): int
    {
        $extends = $this->option('extends');

        if (! is_string($extends) || ! isset(self::BASES[$extends])) {
            $this->components->error('The field type must be one of: '.implode(', ', array_keys(self::BASES)).'.');

            return self::FAILURE;
        }

        return parent::handle($files);
    }

    protected function stub(): string
    {
        return 'field';
    }

    protected function folder(): string
    {
        return 'Fields';
    }

    protected function replacements(string $class): array
    {
        $base = self::BASES[(string) $this->option('extends')];
        $default = (new ReflectionClass($base))->getDefaultProperties()['component'] ?? 'text';

        return [
            'baseImport' => $base,
            'baseClass' => class_basename($base),
            'component' => $this->option('custom') ? Str::kebab($class) : (is_string($default) ? $default : 'text'),
        ];
    }

    protected function scaffoldReact(Filesystem $files, string $class): void
    {
        if ($this->option('custom')) {
            $this->writeComponent($files, 'field-react', 'fields', $class, ['component' => Str::kebab($class)]);
        }
    }
}
