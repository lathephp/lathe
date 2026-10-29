<?php

use Symfony\Component\Process\Process;

// Minutes long and online (Packagist), so it runs only when asked: the release workflow's
// smoke job runs the script itself, and LATHE_RELEASE_SMOKE=1 runs it from Pest.
it('installs the packed release into a clean Laravel app', function () {
    $process = new Process(['bash', 'scripts/release-smoke.sh'], dirname(__DIR__, 3), timeout: 1500);
    $process->run();

    expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput())
        ->and($process->getOutput())->toContain('lathe:install and lathe:doctor passed, /lathe/login answered 200, the Users list opened');
})->skip(fn () => getenv('LATHE_RELEASE_SMOKE') !== '1', 'Set LATHE_RELEASE_SMOKE=1 to install the packed release into a clean app.');

// The CLI server's opcache keeps a file it compiled for opcache.revalidate_freq (2 seconds), so a
// gate edited after the first request can still deny the smoke user.
it('adds the smoke user to the gate before it starts the server', function () {
    $script = (string) file_get_contents(dirname(__DIR__, 3).'/scripts/release-smoke.sh');

    $gate = strpos($script, "'smoke@example.com',|\" app/Providers/LatheServiceProvider.php");
    $serve = strpos($script, 'php artisan serve');

    expect($gate)->toBeInt()
        ->and($serve)->toBeInt()
        ->and($gate)->toBeLessThan($serve);
});
