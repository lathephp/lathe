<?php

namespace Lathe\Console;

use Composer\InstalledVersions;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Support\Str;
use Lathe\Plugins\PluginDiscovery;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Process\Process;

/**
 * Scaffolds a plugin: a Composer package in lathe-components/ that Lathe discovers and shows in the
 * sidebar and the palette, or with --inline a Plugin class and a React page inside the app.
 */
#[AsCommand(name: 'lathe:plugin')]
class MakePluginCommand extends GeneratorCommand
{
    protected $signature = 'lathe:plugin
        {name? : The vendor/package name (acme/price-tracker), or a class name with --inline}
        {--inline : Create an in-app plugin class and React page instead of a Composer package}
        {--force : Overwrite an existing in-app plugin}
        {--skip-composer : Do not offer to run composer update}';

    protected $description = 'Create a Lathe plugin package, or with --inline an in-app plugin';

    public function handle(Filesystem $files): int
    {
        $name = $this->argument('name');

        if (! is_string($name) || $name === '') {
            $this->components->error('Not enough arguments (missing: "name").');

            return self::FAILURE;
        }

        return $this->option('inline') ? parent::handle($files) : $this->scaffoldPackage($files, $name);
    }

    protected function stub(): string
    {
        return 'plugin';
    }

    protected function folder(): string
    {
        return 'Plugins';
    }

    protected function scaffoldReact(Filesystem $files, string $class): void
    {
        $this->writeComponent($files, 'plugin-react', 'plugins', $class, [
            'name' => Str::headline($this->pluginName($class)),
            'component' => Str::kebab($this->pluginName($class)),
        ]);

        $namespace = $this->laravel->getNamespace();

        $this->line("  Register it with <info>Lathe::registerPlugins(new \\{$namespace}Lathe\\Plugins\\{$class})</info>.");
    }

    /**
     * The class name without a trailing Plugin, as Plugin::name() and Plugin::uriKey() read it.
     */
    private function pluginName(string $class): string
    {
        return $class !== 'Plugin' && Str::endsWith($class, 'Plugin') ? Str::beforeLast($class, 'Plugin') : $class;
    }

    private function scaffoldPackage(Filesystem $files, string $slug): int
    {
        $parts = explode('/', $slug, 2);
        $vendor = $parts[0];
        $package = $parts[1] ?? '';

        if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $vendor) !== 1 || preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $package) !== 1) {
            $this->components->error('Give the plugin as vendor/package. Both are lowercase letters, digits and hyphens, and the package starts with a letter.');

            return self::FAILURE;
        }

        $version = $this->installedVersion();

        if ($version === null) {
            $this->components->error('The installed version of lathe/lathe could not be read.');

            return self::FAILURE;
        }

        $host = $this->hostPath();
        $class = Str::studly($package);
        $target = "{$host}/lathe-components/{$class}";

        if ($files->isDirectory($target)) {
            $this->components->error("lathe-components/{$class} already exists.");

            return self::FAILURE;
        }

        $namespace = Str::studly($vendor).'\\'.$class;

        $this->renderSkeleton($files, $target, $class, [
            'name' => $slug,
            'vendor' => $vendor,
            'package' => $package,
            'class' => $class,
            'title' => Str::headline($package),
            'component' => $package,
            'namespace' => $namespace,
            'escapedNamespace' => str_replace('\\', '\\\\', $namespace),
            'lathe' => $this->isDevelopment($version) ? '@dev' : '^'.explode('.', $version)[0],
            'npm' => $this->npmVersion(),
            'author' => $this->author($host),
            'year' => date('Y'),
        ]);

        $this->components->info("lathe-components/{$class} created.");

        $this->wireComposer($files, "{$host}/composer.json", $slug);
        $this->addWorkspace($files, "{$host}/pnpm-workspace.yaml");

        if (! $files->exists("{$host}/lathe-components/README.md")) {
            $files->copy(__DIR__.'/../../stubs/lathe-components-readme.md', "{$host}/lathe-components/README.md");
        }

        if (! $this->option('skip-composer') && $this->input->isInteractive() && $this->components->confirm("Run composer update {$slug} now?")) {
            $this->runComposerUpdate($slug);
        }

        $this->line("  Build its page: <info>cd lathe-components/{$class} && pnpm install && pnpm build</info>");

        return self::SUCCESS;
    }

    /**
     * Copy the skeleton to the target, filling the placeholders and naming the PHP files.
     *
     * @param  array<string, string>  $replace
     */
    private function renderSkeleton(Filesystem $files, string $target, string $class, array $replace): void
    {
        $skeleton = __DIR__.'/../../stubs/plugin-skeleton';

        $names = [
            'src/Plugin.stub' => "src/{$class}.php",
            'src/PluginSearch.stub' => "src/{$class}Search.php",
            'config/config.stub' => "config/{$replace['package']}.php",
            'tests/Feature/PluginTest.stub' => "tests/Feature/{$class}Test.php",
        ];

        foreach ($files->allFiles($skeleton, true) as $file) {
            $relative = $file->getRelativePathname();
            $path = $names[$relative] ?? preg_replace('/\.stub$/', '.php', $relative);
            $contents = $file->getContents();

            foreach ($replace as $key => $value) {
                $contents = str_replace("{{ {$key} }}", $value, $contents);
            }

            $files->ensureDirectoryExists(dirname("{$target}/{$path}"));
            $files->put("{$target}/{$path}", $contents);
        }
    }

    /**
     * Add the lathe-components path repository and the package to the host composer.json.
     */
    private function wireComposer(Filesystem $files, string $path, string $slug): void
    {
        $composer = json_decode($files->get($path), true);
        $composer = is_array($composer) ? $composer : [];

        $repositories = is_array($composer['repositories'] ?? null) ? $composer['repositories'] : [];
        $repository = ['type' => 'path', 'url' => 'lathe-components/*'];

        if (! in_array($repository, $repositories, true)) {
            $repositories[] = $repository;
        }

        $require = is_array($composer['require'] ?? null) ? $composer['require'] : [];
        $require[$slug] = '@dev';

        $composer['repositories'] = $repositories;
        $composer['require'] = $require;

        $files->put($path, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
    }

    /**
     * List lathe-components/* under packages: in pnpm-workspace.yaml, creating the file when missing.
     */
    private function addWorkspace(Filesystem $files, string $path): void
    {
        $entry = '  - lathe-components/*';
        $yaml = $files->exists($path) ? $files->get($path) : '';

        if (preg_match('/^\s*-\s*[\'"]?lathe-components\/\*[\'"]?\s*$/m', $yaml) === 1) {
            return;
        }

        if (preg_match('/^packages:[ \t]*\n(?:[ \t]*-[^\n]*\n?)*/m', $yaml, $match) === 1) {
            $block = str_ends_with($match[0], "\n") ? $match[0] : $match[0]."\n";
            $yaml = str_replace($match[0], "{$block}{$entry}\n", $yaml);
        } else {
            $yaml .= ($yaml === '' || str_ends_with($yaml, "\n") ? '' : "\n")."packages:\n{$entry}\n";
        }

        $files->put($path, $yaml);
    }

    /**
     * The folder of the project whose Composer autoloader runs. A test binds lathe.host_path to
     * write somewhere else.
     */
    protected function hostPath(): string
    {
        $bound = $this->laravel->bound('lathe.host_path') ? $this->laravel->make('lathe.host_path') : null;

        if (is_string($bound)) {
            return $bound;
        }

        return dirname(PluginDiscovery::vendorPath($this->laravel->make(PackageManifest::class)->vendorPath));
    }

    /**
     * The normalised installed version of lathe/lathe, null when Composer does not know it.
     */
    protected function installedVersion(): ?string
    {
        return InstalledVersions::isInstalled('lathe/lathe') ? InstalledVersions::getVersion('lathe/lathe') : null;
    }

    /**
     * A development checkout reports dev-main or 1.0.x-dev.
     */
    private function isDevelopment(string $version): bool
    {
        return str_starts_with($version, 'dev-') || str_ends_with($version, '-dev');
    }

    /**
     * The version of the @lathephp packages that ship with this package.
     */
    private function npmVersion(): string
    {
        $core = json_decode((string) file_get_contents(__DIR__.'/../../packages/core/package.json'), true);

        return is_array($core) && is_string($core['version'] ?? null) ? $core['version'] : 'latest';
    }

    /**
     * The name in git config for the licence line, a placeholder when there is none.
     */
    private function author(string $host): string
    {
        $process = new Process(['git', 'config', 'user.name'], is_dir($host) ? $host : null);
        $process->run();

        return trim($process->getOutput()) ?: 'Your Name';
    }

    protected function runComposerUpdate(string $package): void
    {
        $process = new Process(['composer', 'update', $package], $this->hostPath());
        $process->setTimeout(null);
        $process->run(fn ($type, $line) => $this->output->write($line));
    }
}
