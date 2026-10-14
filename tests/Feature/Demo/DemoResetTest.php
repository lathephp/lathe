<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/*
 * The live demo's nightly reset: scripts/demo/reset.sh takes the app down, restores the
 * zstd snapshot into MySQL and brings the app up. This runs its steps against the MySQL
 * lane, in a database of its own, through a scratch app folder whose artisan is the
 * workbench's and whose .env names that database.
 */

const DEMO_RESET_DATABASE = 'lathe_test_demo_reset';

function demoResetRepo(): string
{
    return dirname(__DIR__, 3);
}

/**
 * The lane's server, or null when it is not running.
 */
function demoResetServer(): ?PDO
{
    try {
        return new PDO('mysql:host=127.0.0.1;port=33384', 'lathe', 'lathe_secret', [PDO::ATTR_TIMEOUT => 2]);
    } catch (PDOException) {
        return null;
    }
}

/**
 * A scratch app folder: an artisan that runs the workbench's, a .env for the lane database,
 * its own storage folder for the maintenance flag, and a bin folder whose mysql and mysqldump
 * run inside the lane container, since the host may have no MySQL client.
 */
function demoResetApp(): string
{
    $repo = demoResetRepo();
    $dir = sys_get_temp_dir().'/lathe-demo-reset-'.bin2hex(random_bytes(4));

    File::ensureDirectoryExists("{$dir}/storage/framework");
    File::ensureDirectoryExists("{$dir}/bin");
    File::put("{$dir}/artisan", '<?php chdir('.var_export($repo, true).'); passthru(implode(" ", array_map("escapeshellarg", [PHP_BINARY, "vendor/bin/testbench", ...array_slice($argv, 1)])), $code); exit($code);');
    File::put("{$dir}/.env", implode("\n", [
        'DB_CONNECTION=mysql',
        'DB_HOST=127.0.0.1',
        'DB_PORT=33384',
        'DB_DATABASE='.DEMO_RESET_DATABASE,
        'DB_USERNAME=lathe',
        'DB_PASSWORD=lathe_secret',
    ])."\n");

    foreach (['mysql', 'mysqldump'] as $client) {
        // The container listens on its own socket, so the host and port the script passes are dropped.
        File::put("{$dir}/bin/{$client}", "#!/usr/bin/env bash\nargs=()\nfor arg; do case \$arg in --host=*|--port=*) ;; *) args+=(\"\$arg\") ;; esac; done\nexec podman exec -i -e MYSQL_PWD lathe-mysql84 {$client} \"\${args[@]}\"\n");
        chmod("{$dir}/bin/{$client}", 0755);
    }

    return $dir;
}

/**
 * Runs reset.sh with the given steps for the scratch app and returns the finished process.
 */
function demoReset(string $app, string ...$steps): Process
{
    $process = new Process(['bash', demoResetRepo().'/scripts/demo/reset.sh', ...$steps], demoResetRepo(), [
        'LATHE_DEMO_APP' => $app,
        'LATHE_DEMO_SNAPSHOT' => "{$app}/snapshot.sql.zst",
        'LARAVEL_STORAGE_PATH' => "{$app}/storage",
        'PATH' => "{$app}/bin:".getenv('PATH'),
    ], timeout: 120);
    $process->run();

    return $process;
}

/**
 * Runs a workbench command against the lane database.
 */
function demoResetArtisan(string ...$arguments): Process
{
    return (new Process([PHP_BINARY, 'vendor/bin/testbench', ...$arguments], demoResetRepo(), [
        'DB_CONNECTION' => 'mysql',
        'DB_HOST' => '127.0.0.1',
        'DB_PORT' => '33384',
        'DB_DATABASE' => DEMO_RESET_DATABASE,
        'DB_USERNAME' => 'lathe',
        'DB_PASSWORD' => 'lathe_secret',
    ], timeout: 120))->mustRun();
}

it('restores the 1,000-order snapshot and answers 503 while it runs', function () {
    $server = demoResetServer();

    if ($server === null) {
        $this->markTestSkipped('The MySQL lane is not running on 127.0.0.1:33384; start it with: bash scripts/lanes.sh up mysql');
    }

    if ((new Process(['zstd', '--version']))->run() !== 0) {
        $this->markTestSkipped('zstd is not installed; reset.sh compresses the snapshot with it.');
    }

    $database = DEMO_RESET_DATABASE;
    $server->exec("drop database if exists `{$database}`");
    $server->exec("create database `{$database}`");
    $app = demoResetApp();

    try {
        demoResetArtisan('migrate', '--force');
        demoResetArtisan('lathe:demo-seed', '--orders=1000');

        $snapshot = demoReset($app, 'snapshot');
        expect($snapshot->getExitCode())->toBe(0, $snapshot->getErrorOutput().$snapshot->getOutput());

        // A visitor's day: ten orders deleted, which the reset must bring back.
        $server->exec("delete from `{$database}`.orders where id <= 10");
        expect((int) $server->query("select count(*) from `{$database}`.orders")->fetchColumn())->toBe(990);

        $down = demoReset($app, 'down');
        expect($down->getExitCode())->toBe(0, $down->getErrorOutput().$down->getOutput());
        $this->app->useStoragePath("{$app}/storage");
        $this->get('/lathe/login')->assertStatus(503);

        $restore = demoReset($app, 'restore');
        expect($restore->getExitCode())->toBe(0, $restore->getErrorOutput().$restore->getOutput())
            ->and((int) $server->query("select count(*) from `{$database}`.orders")->fetchColumn())->toBe(1000);
        $this->get('/lathe/login')->assertStatus(503);

        $up = demoReset($app, 'up');
        expect($up->getExitCode())->toBe(0, $up->getErrorOutput().$up->getOutput());
        $this->get('/lathe/login')->assertOk();
    } finally {
        $server->exec("drop database if exists `{$database}`");
        File::deleteDirectory($app);
    }
})->group('demo');
