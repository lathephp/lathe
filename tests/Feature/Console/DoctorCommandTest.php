<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Lathe\Doctor\Doctor;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Search\SearchableText;
use Lathe\Tests\Fixtures\Plugins\Beacon;
use Lathe\Tests\Fixtures\Plugins\BillingPlugin;
use Workbench\App\Lathe\Resources\Customer;
use Workbench\App\Models\Post;

/**
 * A doctor that reads a fixed row count for every table instead of counting.
 */
class FixedRowsDoctor extends Doctor
{
    public static int $rows = 0;

    protected function rowCount(Model $model): int
    {
        return self::$rows;
    }
}

/**
 * Full text search on posts.title, which has no FULLTEXT index.
 */
class DoctorTitleTextPost extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Post::class)->canonical(false)->searchable([new SearchableText('title')])->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    config(['queue.default' => 'database']);
    Gate::define('viewLathe', fn () => true);

    $this->dist = sys_get_temp_dir().'/lathe-dist-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists("{$this->dist}/assets");
    File::put("{$this->dist}/assets/app-abc12345.js", '');
    File::put("{$this->dist}/manifest.json", json_encode(['resources/js/app.tsx' => ['file' => 'assets/app-abc12345.js']]));
    Lathe::assets()->useDistPath($this->dist);
});

afterEach(function () {
    File::deleteDirectory($this->dist);
    Lathe::resources()->clearCache();
});

/**
 * @return array{0: int, 1: list<string>}
 */
class DoctorQueuedUpload extends \Lathe\Actions\Action implements \Illuminate\Contracts\Queue\ShouldQueue
{
    public function fields(LatheRequest $request): array
    {
        return [\Lathe\Field\Field::file('Manifest')];
    }
}

class DoctorQueuedUploadPosts extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Post::class)->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }

    public function actions(LatheRequest $request): array
    {
        return [DoctorQueuedUpload::make()];
    }
}

function doctor(): array
{
    $code = Artisan::call('lathe:doctor');

    return [$code, array_values(array_filter(explode("\n", Artisan::output())))];
}

it('prints one line per check in order and a count, and exits 0 on a healthy setup', function () {
    $this->artisan('lathe:cache')->assertSuccessful();

    [$code, $lines] = doctor();

    expect($code)->toBe(0)
        ->and(end($lines))->toMatch('/^\d+ OK, 0 WARN, 0 FAIL$/')
        ->and(array_slice($lines, 0, -1))->each->toMatch('/^(OK|WARN|FAIL|SKIP) +\S/');

    $order = array_map(fn (string $needle) => collect($lines)->search(fn ($line) => str_contains($line, $needle)), [
        'PHP ', 'config/lathe.php', 'Table lathe_bulk_runs', 'Resources', 'Plugin ', 'Built assets',
    ]);
    expect($order)->toBe(collect($order)->sort()->values()->all());
});

it('fails once with the build hint when the manifest is gone', function () {
    File::move("{$this->dist}/manifest.json", "{$this->dist}/manifest.old");

    [$code, $lines] = doctor();
    $fails = array_values(array_filter($lines, fn ($line) => str_starts_with($line, 'FAIL')));

    expect($code)->toBe(1)
        ->and($fails)->toHaveCount(1)
        ->and($fails[0])->toContain('run pnpm build');
});

it('prints a FAIL for each bad config value', function () {
    config(['lathe.table.density' => 'roomy', 'lathe.table.striped' => 'yes']);

    [$code, $lines] = doctor();

    expect($code)->toBe(1)
        ->and(array_filter($lines, fn ($line) => str_starts_with($line, 'FAIL config/lathe.php')))->toHaveCount(2);
});

it('warns about an unknown config key and still exits 0', function () {
    $this->artisan('lathe:cache');
    config(['lathe.colour' => 'blue']);

    [$code, $lines] = doctor();

    expect($code)->toBe(0)
        ->and($lines)->toContain('WARN config/lathe.php: unknown key colour. Remove it or check its spelling.');
});

it('fails when the viewLathe gate is undefined', function () {
    forgetGate('viewLathe');

    [$code, $lines] = doctor();

    expect($code)->toBe(1)
        ->and(implode("\n", $lines))->toContain('FAIL The viewLathe gate is not defined');
});

it('skips every table check when the database is unreachable', function () {
    config(['database.connections.broken' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'x', 'username' => 'x', 'password' => 'x']]);
    config(['database.default' => 'broken']);

    [$code, $lines] = doctor();

    expect($code)->toBe(1)
        ->and(array_filter($lines, fn ($line) => str_starts_with($line, 'SKIP Table ')))->toHaveCount(10);
});

it('fails on a plugin requirement, a missing range and a range that misses the installed version', function () {
    $vendor = sys_get_temp_dir().'/lathe-vendor-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists("{$vendor}/composer");
    File::put("{$vendor}/composer/installed.json", json_encode(['packages' => [
        ['name' => 'acme/beacon', 'require' => ['lathe/lathe' => '^9.0'], 'extra' => ['lathe' => ['plugin' => Beacon::class]]],
    ]]));
    app(PackageManifest::class)->vendorPath = $vendor;
    Lathe::registerPlugins(Beacon::make());

    [, $lines] = doctor();
    expect(implode("\n", $lines))->toContain('FAIL Plugin '.Beacon::class.' (acme/beacon) needs lathe/lathe ^9.0');

    File::put("{$vendor}/composer/installed.json", json_encode(['packages' => [
        ['name' => 'acme/beacon', 'require' => [], 'extra' => ['lathe' => ['plugin' => Beacon::class]]],
    ]]));
    [, $lines] = doctor();
    expect(implode("\n", $lines))->toContain('declares no lathe/lathe range');

    $plugins = Lathe::plugins();
    (fn () => $this->plugins['billing'] = BillingPlugin::make())->call($plugins);
    [$code, $lines] = doctor();
    expect($code)->toBe(1)
        ->and(implode("\n", $lines))->toContain('FAIL Plugin '.BillingPlugin::class.' requires Acme\\Missing\\Ledger');
});

it('fails on a plugin manifest whose core range misses the installed @lathephp/core', function () {
    $dir = sys_get_temp_dir().'/lathe-plugin-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($dir);
    File::put("{$dir}/beacon.js", '');
    File::put("{$dir}/lathe-plugin.json", json_encode(['name' => 'beacon', 'core' => '^9.0']));
    $plugin = new class extends Beacon
    {
        public static string $script = '';

        public static function uriKey(): string
        {
            return 'beacon';
        }

        public function assets(): array
        {
            return ['scripts' => ['beacon' => self::$script]];
        }
    };
    $plugin::$script = "{$dir}/beacon.js";
    Lathe::registerPlugins($plugin);

    [$code, $lines] = doctor();
    expect($code)->toBe(1)
        ->and(implode("\n", $lines))->toContain("FAIL Plugin beacon: {$dir}/lathe-plugin.json needs @lathephp/core ^9.0, but ");

    File::put("{$dir}/lathe-plugin.json", json_encode(['name' => 'beacon', 'core' => '*']));
    [, $lines] = doctor();
    expect($lines)->toContain("OK   Plugin beacon: {$dir}/lathe-plugin.json (@lathephp/core *)");
});

it('warns about a contains search column on a table of more than 100,000 rows, except on SQLite', function () {
    $this->artisan('lathe:cache')->assertSuccessful();
    app()->bind(Doctor::class, FixedRowsDoctor::class);
    $warning = 'WARN '.Customer::class.': search column email matches anywhere (contains) in the customers table, which has more than 100,000 rows, so every search reads the whole table. Search it by prefix or with SearchableText.';

    FixedRowsDoctor::$rows = 100_000;
    [, $lines] = doctor();
    expect($lines)->not->toContain($warning);

    FixedRowsDoctor::$rows = 100_001;
    [$code, $lines] = doctor();
    $sqlite = DB::connection()->getDriverName() === 'sqlite';

    expect($code)->toBe(0)
        ->and(end($lines))->toMatch('/ 0 FAIL$/')
        ->and(in_array($warning, $lines, true))->toBe(! $sqlite);
});

it('fails a full text search column the driver cannot run', function () {
    Lathe::registerResources(DoctorTitleTextPost::class);
    $driver = DB::connection()->getDriverName();
    $fail = 'FAIL '.DoctorTitleTextPost::class.": SearchableText(title) cannot run on {$driver}: the posts table has no FULLTEXT index on title alone. Add \$table->fullText('title') in a migration.";

    [$code, $lines] = doctor();

    if (in_array($driver, ['mysql', 'mariadb'], true)) {
        expect($code)->toBe(1)->and($lines)->toContain($fail);
    } else {
        expect(array_filter($lines, fn ($line) => str_starts_with($line, 'FAIL')))->toBe([]);
    }
});

it('fails a queued action with a file field', function () {
    Lathe::registerResources(DoctorQueuedUploadPosts::class);

    [$code, $lines] = doctor();

    expect($code)->toBe(1)
        ->and(array_values(array_filter($lines, fn (string $line) => str_starts_with($line, 'FAIL') && str_contains($line, DoctorQueuedUploadPosts::class))))->toHaveCount(1);
});

it('writes no file and makes no network call', function () {
    Http::preventStrayRequests();
    $before = snapshotTree([base_path(), $this->dist]);

    doctor();

    expect(snapshotTree([base_path(), $this->dist]))->toBe($before);
});

/**
 * Every file under the given folders with its size and modification time.
 *
 * @param  list<string>  $folders
 * @return array<string, string>
 */
function snapshotTree(array $folders): array
{
    $files = [];
    foreach ($folders as $folder) {
        foreach (File::allFiles($folder) as $file) {
            // Parallel test processes write sessions, logs and the package cache under the shared skeleton.
            if (str_starts_with($file->getPathname(), base_path('storage')) || str_starts_with($file->getPathname(), base_path('bootstrap/cache'))) {
                continue;
            }

            $files[$file->getPathname()] = $file->getSize().':'.$file->getMTime();
        }
    }
    ksort($files);

    return $files;
}
