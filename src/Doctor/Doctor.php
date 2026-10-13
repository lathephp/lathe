<?php

namespace Lathe\Doctor;

use Composer\InstalledVersions;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lathe\Assets\AssetManifest;
use Lathe\Exceptions\LatheException;
use Lathe\Plugins\Plugin;
use Lathe\Plugins\PluginRegistry;
use Lathe\Resources\ResourceRegistry;
use Lathe\Support\ConfigStore;
use Lathe\Support\ConfigValidator;
use Throwable;

/**
 * Reads the whole setup and says what is wrong. Writes nothing and makes no network call.
 */
class Doctor
{
    /** @var list<string> */
    public const array TABLES = [
        'lathe_pending_uploads', 'lathe_action_events', 'lathe_bulk_runs', 'lathe_saved_tabs', 'notifications',
        'lathe_exports', 'lathe_imports', 'lathe_failed_import_rows', 'lathe_field_attachments', 'lathe_plugin_settings',
    ];

    public function __construct(
        protected Application $app,
        protected ConfigStore $config,
        protected ConfigValidator $validator,
        protected ResourceRegistry $resources,
        protected PluginRegistry $plugins,
        protected Gate $gate,
    ) {
        //
    }

    /**
     * Every check, in the order environment, config, database, Resources, plugins, assets.
     *
     * @return list<CheckResult>
     */
    public function run(): array
    {
        return [
            ...$this->environment(),
            ...$this->configuration(),
            ...$this->database(),
            ...$this->resourceChecks(),
            ...$this->pluginChecks(),
            ...$this->assets(),
        ];
    }

    /**
     * @return list<CheckResult>
     */
    protected function environment(): array
    {
        $laravel = $this->app->version();

        return [
            version_compare(PHP_VERSION, '8.4.0', '>=')
                ? CheckResult::ok('PHP '.PHP_VERSION)
                : CheckResult::fail('PHP '.PHP_VERSION.' is below 8.4. Upgrade PHP to 8.4 or newer.'),
            Semver::satisfies($laravel, '^13.0')
                ? CheckResult::ok("Laravel {$laravel}")
                : CheckResult::fail("Laravel {$laravel} is outside the supported range ^13.0. Use Laravel 13."),
        ];
    }

    /**
     * @return list<CheckResult>
     */
    protected function configuration(): array
    {
        $results = array_map(fn (string $error) => CheckResult::fail($error), $this->validator->errors($this->config->all()));
        $results = $results === [] ? [CheckResult::ok('config/lathe.php has no errors')] : $results;

        foreach ($this->validator->unknownKeys($this->config->all()) as $key) {
            $results[] = CheckResult::warn("config/lathe.php: unknown key {$key}. Remove it or check its spelling.");
        }

        $guard = $this->config->nullableString('guard') ?? $this->stringConfig('auth.defaults.guard');
        $provider = $this->stringConfig("auth.guards.{$guard}.provider");
        $model = $this->config("auth.providers.{$provider}.model");
        $broker = $this->config->nullableString('passwords') ?? $this->stringConfig('auth.defaults.passwords');

        $results[] = $provider !== ''
            ? CheckResult::ok("Guard {$guard}")
            : CheckResult::fail("Guard {$guard} is not defined in config/auth.php. Define it or set lathe.guard.");
        $results[] = is_string($model) && is_subclass_of($model, Model::class)
            ? CheckResult::ok("User model {$model}")
            : CheckResult::fail("The user model of guard {$guard} cannot be found. Set auth.providers.{$provider}.model.");
        $results[] = $this->config("auth.passwords.{$broker}") !== null
            ? CheckResult::ok("Password broker {$broker}")
            : CheckResult::fail("Password broker {$broker} is not defined in config/auth.php. Define it or set lathe.passwords.");
        $results[] = $this->gate->has('viewLathe')
            ? CheckResult::ok('The viewLathe gate is defined')
            : CheckResult::fail('The viewLathe gate is not defined, so nobody can open the panel. Define it in the gate() method of App\Providers\LatheServiceProvider.');

        foreach (['storage_disk', 'exports.disk', 'imports.disk'] as $key) {
            $disk = $this->config->string($key);
            $results[] = $this->config("filesystems.disks.{$disk}") !== null
                ? CheckResult::ok("Disk {$disk} ({$key})")
                : CheckResult::fail("Disk {$disk} named by {$key} is not defined in config/filesystems.php. Define it or change {$key}.");
        }

        return $results;
    }

    /**
     * @return list<CheckResult>
     */
    protected function database(): array
    {
        try {
            $connection = DB::connection();
            $connection->getPdo();
        } catch (Throwable $e) {
            return [
                CheckResult::fail('The database is unreachable: '.$e->getMessage().'. Check the DB_* settings.'),
                ...array_map(fn (string $table) => CheckResult::skip("Table {$table}: the database is unreachable"), self::TABLES),
                CheckResult::skip('Queue tables: the database is unreachable'),
            ];
        }

        $results = [$this->driverCheck($connection->getDriverName(), $connection->getServerVersion())];

        foreach (self::TABLES as $table) {
            $results[] = Schema::hasTable($table)
                ? CheckResult::ok("Table {$table}")
                : CheckResult::fail("Table {$table} is missing. Run php artisan migrate.");
        }

        $queue = $this->stringConfig('queue.default');
        $results[] = match (true) {
            $this->config("queue.connections.{$queue}.driver") === 'sync' => CheckResult::warn('The queue connection is sync, so large bulk runs would run inside the request. Use a real queue.'),
            ! Schema::hasTable('job_batches') => CheckResult::warn('The job_batches table is missing, so queued bulk runs would fail. Run php artisan make:queue-batches-table and migrate.'),
            default => CheckResult::ok("Queue {$queue} with job batches"),
        };

        return $results;
    }

    protected function driverCheck(string $driver, string $version): CheckResult
    {
        $minimum = match (true) {
            $driver === 'mysql' && str_contains(strtolower($version), 'mariadb') => ['MariaDB', '10.11'],
            $driver === 'mariadb' => ['MariaDB', '10.11'],
            $driver === 'mysql' => ['MySQL', '8.4'],
            $driver === 'pgsql' => ['PostgreSQL', '15'],
            $driver === 'sqlite' => ['SQLite', '0'],
            default => null,
        };

        if ($minimum === null) {
            return CheckResult::warn("Database driver {$driver} is not supported. Use MySQL, MariaDB, PostgreSQL or SQLite.");
        }

        preg_match('/\d+(\.\d+)*/', $version, $match);

        return version_compare($match[0] ?? '0', $minimum[1], '>=')
            ? CheckResult::ok("Database {$minimum[0]} {$version}")
            : CheckResult::warn("Database {$minimum[0]} {$version} is below {$minimum[1]}. Upgrade the database server.");
    }

    /**
     * @return list<CheckResult>
     */
    protected function resourceChecks(): array
    {
        try {
            $manifest = $this->resources->manifest();
        } catch (LatheException $e) {
            return [CheckResult::fail($e->getMessage())];
        }

        $results = [CheckResult::ok(count($manifest).' Resources, no key or model collision')];

        foreach ($manifest as $entry) {
            $definition = $this->resources->definition($entry['class']);

            if ($definition->allowsAll()) {
                $results[] = CheckResult::warn("{$entry['class']} calls ->allowAll(), so every user who opens the panel can do everything with it. Write a policy instead.");
            } elseif ($this->gate->getPolicyFor($entry['model']) === null) {
                $results[] = CheckResult::warn("{$entry['class']} has no policy, so every ability is denied. Write a policy for {$entry['model']}.");
            }
        }

        $results[] = file_exists($this->resources->cachePath())
            ? CheckResult::ok('Resource discovery cache')
            : CheckResult::warn('The Resource discovery cache is missing. Run php artisan optimize or lathe:cache.');

        return $results;
    }

    /**
     * @return list<CheckResult>
     */
    protected function pluginChecks(): array
    {
        $results = [];
        $parser = new VersionParser;
        $packages = $this->pluginPackages();

        foreach ($this->plugins->all() as $plugin) {
            $class = $plugin::class;
            $missing = array_values(array_filter($plugin->requires(), fn (string $required) => ! class_exists($required) && ! interface_exists($required)));

            if ($missing !== []) {
                $results[] = CheckResult::fail("Plugin {$class} requires ".implode(', ', $missing).', which cannot be found. Install the packages that provide them.');

                continue;
            }

            $package = $packages[$class] ?? null;

            if ($package === null) {
                $results[] = CheckResult::ok("Plugin {$class}");
            } else {
                $range = $package['range'];

                $results[] = match (true) {
                    $range === null => CheckResult::fail("Plugin {$class} ({$package['name']}) declares no lathe/lathe range. Add one, such as ^1.0, to its composer.json require."),
                    ! InstalledVersions::satisfies($parser, 'lathe/lathe', $range) => CheckResult::fail("Plugin {$class} ({$package['name']}) needs lathe/lathe {$range}, but ".InstalledVersions::getPrettyVersion('lathe/lathe').' is installed.'),
                    default => CheckResult::ok("Plugin {$class} ({$package['name']}, lathe/lathe {$range})"),
                };
            }

            array_push($results, ...$this->manifestChecks($plugin));
        }

        return $results === [] ? [CheckResult::ok('No plugins')] : $results;
    }

    /**
     * One row per built plugin manifest, the lathe-plugin.json next to a registered script:
     * its core range against the installed @lathephp/core.
     *
     * @return list<CheckResult>
     */
    protected function manifestChecks(Plugin $plugin): array
    {
        $results = [];
        $key = $plugin::uriKey();
        $core = $this->coreVersion();
        $files = array_map(
            fn (string $script) => (realpath(dirname($script)) ?: dirname($script)).'/lathe-plugin.json',
            $plugin->assets()['scripts'] ?? [],
        );

        foreach (array_unique($files) as $file) {
            $manifest = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
            $range = is_array($manifest) ? ($manifest['core'] ?? null) : null;

            if (! is_string($range)) {
                continue;
            }

            $results[] = $core !== null && Semver::satisfies($core, $range)
                ? CheckResult::ok("Plugin {$key}: {$file} (@lathephp/core {$range})")
                : CheckResult::fail("Plugin {$key}: {$file} needs @lathephp/core {$range}, but ".($core ?? 'no version').' is installed. Install a Lathe version the plugin supports, or rebuild the plugin.');
        }

        return $results;
    }

    /**
     * The @lathephp/core version this Lathe package ships.
     */
    protected function coreVersion(): ?string
    {
        $file = dirname(__DIR__, 2).'/packages/core/package.json';
        $package = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $version = is_array($package) ? ($package['version'] ?? null) : null;

        return is_string($version) ? $version : null;
    }

    /**
     * Discovered plugin packages by plugin class, with their lathe/lathe range.
     *
     * @return array<string, array{name: string, range: string|null}>
     */
    protected function pluginPackages(): array
    {
        $file = $this->app->make(PackageManifest::class)->vendorPath.'/composer/installed.json';
        $installed = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
        $packages = is_array($installed) ? ($installed['packages'] ?? $installed) : [];
        $found = [];

        foreach (is_array($packages) ? $packages : [] as $package) {
            $class = is_array($package) ? data_get($package, 'extra.lathe.plugin') : null;
            $range = is_array($package) ? data_get($package, 'require.lathe/lathe') : null;
            $name = is_array($package) ? data_get($package, 'name') : null;

            if (is_string($class)) {
                $found[$class] = ['name' => is_string($name) ? $name : $class, 'range' => is_string($range) ? $range : null];
            }
        }

        return $found;
    }

    /**
     * @return list<CheckResult>
     */
    protected function assets(): array
    {
        $dist = $this->app->make(AssetManifest::class)->distPath();
        $manifest = "{$dist}/manifest.json";

        if (! is_file($manifest)) {
            return [CheckResult::fail('The built assets are missing (dist/manifest.json). In the Lathe package, run pnpm build.')];
        }

        $entries = json_decode((string) file_get_contents($manifest), true);
        $missing = [];

        foreach (is_array($entries) ? $entries : [] as $entry) {
            $file = is_array($entry) ? ($entry['file'] ?? null) : null;

            if (is_string($file) && ! is_file("{$dist}/{$file}")) {
                $missing[] = $file;
            }
        }

        return [$missing === []
            ? CheckResult::ok('Built assets (dist/manifest.json)')
            : CheckResult::fail('Built files are missing: '.implode(', ', $missing).'. Run pnpm build.')];
    }

    protected function config(string $key): mixed
    {
        return $this->app->make('config')->get($key);
    }

    protected function stringConfig(string $key): string
    {
        $value = $this->config($key);

        return is_string($value) ? $value : '';
    }
}
