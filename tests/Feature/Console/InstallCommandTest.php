<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

pest()->group('scaffold');

/**
 * Run a command in the temporary app. The test's own phpunit.xml env would beat the app's .env, so it is removed.
 *
 * @param  list<string>  $command
 */
function inScaffold(array $command, int $timeout = 300): Process
{
    $env = array_fill_keys([
        'APP_ENV', 'APP_KEY', 'APP_URL', 'APP_DEBUG', 'BCRYPT_ROUNDS', 'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME',
        'DB_PASSWORD', 'SESSION_DRIVER', 'QUEUE_CONNECTION', 'CACHE_STORE', 'MAIL_MAILER',
    ], false);

    $process = new Process($command, scaffoldApp(), $env, null, $timeout);
    $process->run();

    return $process;
}

/**
 * A Laravel 13 app in a temp folder that requires this checkout through a path repository, created once per run.
 */
function scaffoldApp(): string
{
    static $app = null;

    if ($app !== null) {
        return $app;
    }

    $root = sys_get_temp_dir().'/lathe-scaffold-'.bin2hex(random_bytes(4));
    $package = dirname(__DIR__, 3);

    $steps = [
        [['composer', 'create-project', 'laravel/laravel', "{$root}/app", '^13.0', '--no-interaction', '--prefer-dist'], $root],
        [['composer', 'config', 'repositories.lathe', json_encode(['type' => 'path', 'url' => $package, 'options' => ['symlink' => true]])], "{$root}/app"],
        [['composer', 'require', 'lathe/lathe:@dev', '--no-interaction'], "{$root}/app"],
        [['git', 'init', '--quiet'], "{$root}/app"],
    ];

    File::ensureDirectoryExists($root);

    foreach ($steps as [$command, $cwd]) {
        $process = new Process($command, $cwd, null, null, 600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(implode(' ', $command)." failed:\n".$process->getErrorOutput());
        }
    }

    return $app = "{$root}/app";
}

/**
 * Commit the app's tree, so git status shows what the next command changed.
 */
function snapshotScaffold(): void
{
    inScaffold(['git', 'add', '-A']);
    inScaffold(['git', '-c', 'user.name=Lathe', '-c', 'user.email=test@lathephp.com', 'commit', '--quiet', '--allow-empty', '-m', 'snapshot']);
}

/**
 * @return list<string>
 */
function changedInScaffold(): array
{
    $status = inScaffold(['git', 'status', '--porcelain', '--untracked-files=all'])->getOutput();

    return array_values(array_map(fn (string $line) => substr($line, 3), array_filter(explode("\n", $status))));
}

afterAll(function () {
    File::deleteDirectory(dirname(scaffoldApp()));
});

it('installs into a fresh Laravel app and changes only app, bootstrap, config and migrations', function () {
    $app = scaffoldApp();
    snapshotScaffold();

    $install = inScaffold(['php', 'artisan', 'lathe:install', '--no-interaction']);

    expect($install->getExitCode())->toBe(0, $install->getOutput().$install->getErrorOutput());

    $changed = changedInScaffold();
    expect($changed)->not->toBeEmpty()
        ->and(array_filter($changed, fn (string $path) => ! preg_match('#^(app|bootstrap|config|database/migrations)/#', $path)))->toBe([]);

    expect(File::get("{$app}/config/lathe.php"))->toBe(File::get(dirname(__DIR__, 3).'/config/lathe.php'))
        ->and(glob("{$app}/database/migrations/2026_01_01_0000*.php"))->toHaveCount(10)
        ->and(require "{$app}/bootstrap/providers.php")->toContain('App\Providers\LatheServiceProvider')
        ->and("{$app}/app/Providers/LatheServiceProvider.php")->toBeFile()
        ->and("{$app}/app/Lathe/Resources/User.php")->toBeFile()
        ->and("{$app}/app/Policies/UserPolicy.php")->toBeFile()
        ->and("{$app}/app/Lathe/Dashboards/Main.php")->toBeFile()
        ->and(File::get("{$app}/app/Providers/LatheServiceProvider.php"))->toContain("Gate::define('viewLathe', fn (\$user) => in_array(\$user->email, [");

    $tables = (new PDO("sqlite:{$app}/database/database.sqlite"))
        ->query("select name from sqlite_master where type = 'table' and name like 'lathe_%' order by name")
        ?->fetchAll(PDO::FETCH_COLUMN);
    expect($tables)->toContain('lathe_bulk_runs', 'lathe_action_events', 'lathe_plugin_settings');

    // The generated classes load, and the User policy lets a panel user list and open users.
    $probe = inScaffold(['php', 'artisan', 'tinker', '--execute', <<<'PHP'
        $user = new App\Models\User(['email' => 'probe@example.com']);
        echo json_encode([
            'model' => App\Lathe\Resources\User::model(),
            'uriKey' => App\Lathe\Resources\User::uriKey(),
            'dashboard' => (new App\Lathe\Dashboards\Main)->name(),
            'dashboardKey' => App\Lathe\Dashboards\Main::uriKey(),
            'viewLathe' => Gate::forUser($user)->allows('viewLathe'),
            'viewAny' => Gate::forUser($user)->allows('viewAny', App\Models\User::class),
            'view' => Gate::forUser($user)->allows('view', $user),
            'delete' => Gate::forUser($user)->allows('delete', $user),
        ]);
        PHP]);

    expect(json_decode($probe->getOutput(), true))->toBe([
        'model' => 'App\Models\User',
        'uriKey' => 'users',
        'dashboard' => 'Dashboard',
        'dashboardKey' => 'main',
        'viewLathe' => false,
        'viewAny' => true,
        'view' => true,
        'delete' => false,
    ], $probe->getOutput().$probe->getErrorOutput());
});

it('leaves every file byte-identical on a second run and ends with the two next steps', function () {
    inScaffold(['php', 'artisan', 'lathe:install', '--no-interaction']);
    // An edit the app made after the first run must survive the second.
    File::append(scaffoldApp().'/app/Providers/LatheServiceProvider.php', "// edited\n");
    snapshotScaffold();

    $again = inScaffold(['php', 'artisan', 'lathe:install', '--no-interaction']);
    $lines = array_slice(array_values(array_filter(array_map('trim', explode("\n", $again->getOutput())))), -2);

    expect($again->getExitCode())->toBe(0, $again->getOutput().$again->getErrorOutput())
        ->and(changedInScaffold())->toBe([])
        ->and($lines)->toHaveCount(2)
        ->and($lines[0])->toContain('lathe:user')
        ->and($lines[1])->toContain('gate()');
});

it('exits 1 and names the migrate task when the database cannot be reached', function () {
    $env = scaffoldApp().'/.env';
    $original = File::get($env);
    File::put($env, preg_replace('/^DB_CONNECTION=.*$/m', "DB_CONNECTION=mysql\nDB_HOST=127.0.0.1\nDB_PORT=1", $original));

    try {
        $install = inScaffold(['php', 'artisan', 'lathe:install', '--no-interaction']);
    } finally {
        File::put($env, $original);
    }

    expect($install->getExitCode())->toBe(1)
        ->and($install->getOutput().$install->getErrorOutput())->toMatch('/Run the migrations.*FAIL/')
        ->and($install->getOutput().$install->getErrorOutput())->toContain('Run the migrations failed');
});

it('serves the prebuilt entry from the package with no node_modules and no publish step', function () {
    $app = scaffoldApp();
    File::put("{$app}/entry-probe.php", <<<'PHP'
    <?php
    require __DIR__.'/vendor/autoload.php';
    $app = require __DIR__.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $url = Lathe\Lathe::assets()->entryUrl('/'.trim(Lathe\Lathe::path(), '/'));
    $response = $kernel->handle(Illuminate\Http\Request::create($url ?? '/missing'));
    echo $url, ' ', $response->getStatusCode(), ' ', $response->headers->get('Cache-Control');
    PHP);

    $probe = inScaffold(['php', 'entry-probe.php']);
    File::delete("{$app}/entry-probe.php");

    expect(is_dir("{$app}/node_modules"))->toBeFalse()
        ->and(is_dir("{$app}/public/vendor/lathe"))->toBeFalse()
        ->and($probe->getOutput())->toMatch('#^/lathe/assets/[0-9a-z]{8}/app-[0-9a-z]{8}\.js 200 immutable#');
});
