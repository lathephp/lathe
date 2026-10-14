<?php

namespace Lathe\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Base of the lathe:make style generators: it normalises the name, finds the target folder and
 * namespace in the app or in a plugin, and renders a stub, the app's published copy first.
 */
abstract class GeneratorCommand extends Command
{
    /**
     * Names that would clash with a Lathe base class a generated file imports.
     *
     * @var list<string>
     */
    protected const RESERVED = ['Action', 'Dashboard', 'Field', 'Filter', 'Lens', 'Plugin', 'Resource', 'Widget'];

    /**
     * The stub's name under stubs/, without the extension.
     */
    abstract protected function stub(): string;

    /**
     * The folder under the app's Lathe folder, which is also the folder under a plugin's src/.
     */
    abstract protected function folder(): string;

    public function handle(Filesystem $files): int
    {
        $name = $this->argument('name');

        if (! is_string($name) || $name === '') {
            $this->components->error('Not enough arguments (missing: "name").');

            return self::FAILURE;
        }

        $segments = explode('/', $name);

        foreach ($segments as $segment) {
            if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $segment) !== 1) {
                $this->components->error('The name may hold letters, digits, underscores and /, and every part starts with a letter.');

                return self::FAILURE;
            }
        }

        $segments = array_map(Str::studly(...), $segments);
        $class = array_pop($segments);

        if (in_array($class, self::RESERVED, true)) {
            $this->components->error("{$class} is a reserved name.");

            return self::FAILURE;
        }

        $target = $this->target();

        if ($target === null) {
            return self::FAILURE;
        }

        $subfolder = $segments === [] ? '' : '/'.implode('/', $segments);
        $namespace = implode('\\', [$target['namespace'], ...$segments]);

        return $this->writeClass($files, $this->stub(), "{$target['path']}{$subfolder}/{$class}.php", [
            'namespace' => $namespace,
            'class' => $class,
            ...$this->replacements($class),
        ]);
    }

    /**
     * Placeholders a generator adds to namespace and class.
     *
     * @return array<string, string>
     */
    protected function replacements(string $class): array
    {
        return [];
    }

    /**
     * Render a stub to a path. An existing file stays as it is unless --force is set.
     *
     * @param  array<string, string>  $replace
     */
    protected function writeClass(Filesystem $files, string $stub, string $path, array $replace): int
    {
        if ($files->exists($path) && ! $this->option('force')) {
            $this->components->error(Str::after($path, $this->laravel->basePath().'/').' already exists.');

            return self::FAILURE;
        }

        $contents = $files->get($this->stubPath($stub));

        foreach ($replace as $key => $value) {
            $contents = str_replace("{{ {$key} }}", $value, $contents);
        }

        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, $contents);

        $this->components->info(Str::after($path, $this->laravel->basePath().'/').' created.');

        return self::SUCCESS;
    }

    /**
     * The app's published copy of a stub when there is one, else the package's.
     */
    protected function stubPath(string $stub): string
    {
        $published = $this->laravel->basePath("stubs/lathe/{$stub}.stub");

        return file_exists($published) ? $published : __DIR__."/../../stubs/{$stub}.stub";
    }

    /**
     * Where the class goes: the plugin named by --plugin, else the app's Lathe folder.
     *
     * @return array{path: string, namespace: string}|null null after printing why not
     */
    protected function target(): ?array
    {
        $plugin = $this->input->hasOption('plugin') ? $this->input->getOption('plugin') : null;

        if (! is_string($plugin) || $plugin === '') {
            return [
                'path' => app_path("Lathe/{$this->folder()}"),
                'namespace' => $this->laravel->getNamespace().'Lathe\\'.$this->folder(),
            ];
        }

        $root = $this->pluginRoot($plugin);

        return $root === null ? null : [
            'path' => "{$root['path']}/{$this->folder()}",
            'namespace' => "{$root['namespace']}\\{$this->folder()}",
        ];
    }

    /**
     * The src/ folder and root namespace of a plugin given as vendor/name, found in
     * lathe-components/{Studly}/ first and then in vendor/{vendor}/{name}/.
     *
     * @return array{path: string, namespace: string}|null null after printing the searched paths
     */
    protected function pluginRoot(string $slug): ?array
    {
        $parts = explode('/', $slug);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            $this->components->error("The plugin must be given as vendor/name, not [{$slug}].");

            return null;
        }

        $candidates = [
            $this->laravel->basePath('lathe-components/'.Str::studly($parts[1])),
            $this->laravel->basePath("vendor/{$slug}"),
        ];

        foreach ($candidates as $candidate) {
            $manifest = is_file("{$candidate}/composer.json") ? json_decode((string) file_get_contents("{$candidate}/composer.json"), true) : null;
            $psr4 = is_array($manifest) && is_array($manifest['autoload'] ?? null) && is_array($manifest['autoload']['psr-4'] ?? null) ? $manifest['autoload']['psr-4'] : [];

            foreach ($psr4 as $namespace => $folder) {
                if (is_string($folder) && trim($folder, '/') === 'src') {
                    return ['path' => "{$candidate}/src", 'namespace' => rtrim((string) $namespace, '\\')];
                }
            }
        }

        $this->components->error("No plugin [{$slug}] with a src/ PSR-4 root found. Searched:");

        foreach ($candidates as $candidate) {
            $this->line("  {$candidate}");
        }

        return null;
    }
}
