<?php

namespace Lathe\Console;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'lathe:resource')]
class MakeResourceCommand extends GeneratorCommand
{
    protected $signature = 'lathe:resource {name? : The class name} {--plugin= : Write into a plugin, as vendor/name} {--force : Overwrite an existing class}';

    protected $description = 'Create a Lathe resource and its policy';

    public function handle(Filesystem $files): int
    {
        $status = parent::handle($files);

        if ($status !== self::SUCCESS || $this->option('plugin')) {
            return $status;
        }

        $model = $this->model();
        $class = class_basename($model);
        $path = app_path("Policies/{$class}Policy.php");

        if (! $files->exists($path)) {
            $this->writeClass($files, 'policy', $path, [
                'namespace' => $this->laravel->getNamespace(),
                'model' => $model,
                'class' => $class,
            ]);
        }

        if (! class_exists($model)) {
            $this->components->warn("The model {$model} does not exist yet. Create it before opening the Resource.");
        }

        return self::SUCCESS;
    }

    protected function stub(): string
    {
        return 'resource';
    }

    protected function folder(): string
    {
        return 'Resources';
    }

    protected function replacements(string $class): array
    {
        return ['modelNamespace' => $this->laravel->getNamespace().'Models\\'.$class];
    }

    /**
     * The model of the Resource just written: the last part of the name in the app's Models folder.
     */
    protected function model(): string
    {
        $name = $this->argument('name');

        return $this->laravel->getNamespace().'Models\\'.Str::studly(class_basename((string) $name));
    }
}
