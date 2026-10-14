<?php

use Illuminate\Support\Facades\File;
use Lathe\Tests\Feature\Console\Generators\GeneratesIntoTempApp;

uses(GeneratesIntoTempApp::class);

beforeEach(function () {
    $base = $this->generatorApp();

    File::ensureDirectoryExists("{$base}/app/Providers");
    File::ensureDirectoryExists("{$base}/app/Lathe/Resources");
    File::ensureDirectoryExists("{$base}/bootstrap");
    File::ensureDirectoryExists("{$base}/config");
    File::ensureDirectoryExists("{$base}/public/vendor/lathe/plugins");
    File::put("{$base}/app/Providers/LatheServiceProvider.php", '<?php');
    File::put("{$base}/app/Lathe/Resources/User.php", '<?php');
    File::put("{$base}/bootstrap/providers.php", "<?php\n\nreturn [\n    App\\Providers\\AppServiceProvider::class,\n    App\\Providers\\LatheServiceProvider::class,\n];\n");
    File::put("{$base}/config/lathe.php", '<?php return [];');
    File::put("{$base}/public/vendor/lathe/plugins/heartbeat.js", '');
    $this->app->useConfigPath("{$base}/config");
    $this->app->usePublicPath("{$base}/public");
    $this->app->useBootstrapPath("{$base}/bootstrap");
});

it('removes the provider, its registration, the config and the copied assets after a yes', function () {
    $base = $this->generatorBase;

    $this->artisan('lathe:uninstall')
        ->expectsConfirmation('Remove the Lathe service provider, config/lathe.php and public/vendor/lathe?', 'yes')
        ->expectsOutputToContain('composer remove lathe/lathe')
        ->assertExitCode(0);

    expect(File::exists("{$base}/app/Providers/LatheServiceProvider.php"))->toBeFalse()
        ->and(File::get("{$base}/bootstrap/providers.php"))->not->toContain('LatheServiceProvider')->toContain('AppServiceProvider')
        ->and(File::exists("{$base}/config/lathe.php"))->toBeFalse()
        ->and(File::isDirectory("{$base}/public/vendor/lathe"))->toBeFalse()
        ->and(File::exists("{$base}/app/Lathe/Resources/User.php"))->toBeTrue();
});

it('removes nothing on the default no, or without a terminal', function () {
    $base = $this->generatorBase;

    $this->artisan('lathe:uninstall')
        ->expectsConfirmation('Remove the Lathe service provider, config/lathe.php and public/vendor/lathe?', 'no')
        ->assertExitCode(0);
    $this->artisan('lathe:uninstall', ['--no-interaction' => true])->assertExitCode(0);

    expect(File::exists("{$base}/app/Providers/LatheServiceProvider.php"))->toBeTrue()
        ->and(File::exists("{$base}/config/lathe.php"))->toBeTrue()
        ->and(File::isDirectory("{$base}/public/vendor/lathe"))->toBeTrue();
});
