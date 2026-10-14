<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Lathe\Console\MakePluginCommand;
use Lathe\Tests\Feature\Console\Generators\GeneratesIntoTempApp;

uses(GeneratesIntoTempApp::class);

beforeEach(function () {
    $this->generatorApp();
    // The command writes into the project whose autoloader runs; a test points it at the temp app.
    $this->app->instance('lathe.host_path', $this->generatorBase);
});

it('writes the package, wires the host composer.json and the pnpm workspace', function () {
    $this->artisan('lathe:plugin', ['name' => 'acme/price-tracker', '--skip-composer' => true])->assertExitCode(0);

    $package = json_decode($this->generated('lathe-components/PriceTracker/composer.json'), true);
    $host = json_decode($this->generated('composer.json'), true);

    expect($package['name'])->toBe('acme/price-tracker')
        ->and($package['license'])->toBe('MIT')
        ->and($package['extra']['lathe']['plugin'])->toBe('Acme\\PriceTracker\\PriceTracker')
        ->and($package['autoload']['psr-4'])->toBe(['Acme\\PriceTracker\\' => 'src/'])
        ->and($package['require']['lathe/lathe'])->toBe('@dev')
        ->and($host['repositories'])->toBe([['type' => 'path', 'url' => 'lathe-components/*']])
        ->and($host['require'])->toBe(['acme/price-tracker' => '@dev'])
        ->and($this->generated('pnpm-workspace.yaml'))->toBe("packages:\n  - lathe-components/*\n");
});

it('refuses a vendor or package that is not lowercase letters, digits and hyphens', function (string $name) {
    $this->artisan('lathe:plugin', ['name' => $name, '--skip-composer' => true])->assertExitCode(1);

    expect(File::exists("{$this->generatorBase}/lathe-components"))->toBeFalse();
})->with(['Acme/Price_Tracker', 'acme/Price-Tracker', 'acme', 'acme/-price']);

it('refuses a plugin folder that exists', function () {
    File::ensureDirectoryExists("{$this->generatorBase}/lathe-components/PriceTracker");

    $this->artisan('lathe:plugin', ['name' => 'acme/price-tracker', '--skip-composer' => true])->assertExitCode(1);

    expect(File::exists("{$this->generatorBase}/pnpm-workspace.yaml"))->toBeFalse();
});

it('exits 1 and names the missing name without interaction', function () {
    $this->artisan('lathe:plugin', ['--no-interaction' => true])
        ->expectsOutputToContain('missing: "name"')
        ->assertExitCode(1);
});

it('adds the workspace to an existing pnpm-workspace.yaml once', function () {
    File::put("{$this->generatorBase}/pnpm-workspace.yaml", "packages:\n  - packages/*\nincludeWorkspaceRoot: true\n");

    $this->artisan('lathe:plugin', ['name' => 'acme/one', '--skip-composer' => true])->assertExitCode(0);
    $this->artisan('lathe:plugin', ['name' => 'acme/two', '--skip-composer' => true])->assertExitCode(0);

    expect($this->generated('pnpm-workspace.yaml'))->toBe("packages:\n  - packages/*\n  - lathe-components/*\nincludeWorkspaceRoot: true\n");
});

it('writes the plugin class, a search provider, routes and config', function () {
    $this->artisan('lathe:plugin', ['name' => 'acme/price-tracker', '--skip-composer' => true])->assertExitCode(0);

    $plugin = $this->generated('lathe-components/PriceTracker/src/PriceTracker.php');
    $search = $this->generated('lathe-components/PriceTracker/src/PriceTrackerSearch.php');

    expect($plugin)->toContain("namespace Acme\\PriceTracker;\n")
        ->toContain('class PriceTracker extends Plugin')
        ->toContain('MenuItem::plugin(static::class)')
        ->toContain('Lathe::registerSearchProviders(new PriceTrackerSearch($this))')
        ->and($search)->toContain('class PriceTrackerSearch extends SearchProvider')
        ->toContain('GlobalSearchResult::make(')
        // The palette prefixes the panel path, so a row URL starts with a slash.
        ->toContain("->url('/plugins/'.PriceTracker::uriKey())")
        ->toContain('$this->plugin->authorize($request)')
        ->and(File::exists("{$this->generatorBase}/lathe-components/PriceTracker/routes/api.php"))->toBeTrue()
        ->and($this->generated('lathe-components/PriceTracker/config/price-tracker.php'))->toContain('return [');
});

it('writes a vite config of its own that builds an ES module', function () {
    $this->artisan('lathe:plugin', ['name' => 'acme/price-tracker', '--skip-composer' => true])->assertExitCode(0);

    $vite = $this->generated('lathe-components/PriceTracker/vite.config.ts');
    $package = json_decode($this->generated('lathe-components/PriceTracker/package.json'), true);

    expect($vite)->toContain("lathe({ mode: 'plugin', name: 'price-tracker', entry: 'resources/js/plugin.tsx' })")
        ->and($package['name'])->toBe('@acme/price-tracker')
        ->and($package['scripts']['build'])->toBe('vite build')
        ->and($package['devDependencies'])->toHaveKeys(['@lathephp/core', '@lathephp/ui', '@lathephp/vite-plugin', 'react', 'vite'])
        ->and(File::exists("{$this->generatorBase}/lathe-components/PriceTracker/tsconfig.json"))->toBeTrue();
});

it('registers a placeholder page under the uri key from core and ui', function () {
    $this->artisan('lathe:plugin', ['name' => 'acme/price-tracker', '--skip-composer' => true])->assertExitCode(0);

    expect($this->generated('lathe-components/PriceTracker/resources/js/plugin.tsx'))
        ->toContain("import { plugin } from '@lathephp/core';")
        ->toContain("import { Card } from '@lathephp/ui';")
        ->toContain("plugin('price-tracker', Page);")
        ->toContain('Price Tracker');
});

it('writes a test on the test kit, a readme, a changelog and a licence', function () {
    $this->artisan('lathe:plugin', ['name' => 'acme/price-tracker', '--skip-composer' => true])->assertExitCode(0);

    $root = 'lathe-components/PriceTracker';

    expect($this->generated("{$root}/tests/TestCase.php"))->toContain('use Lathe\\Testing\\TestCase as LatheTestCase;')
        ->and($this->generated("{$root}/tests/Feature/PriceTrackerTest.php"))->toContain('PriceTracker')
        ->and($this->generated("{$root}/README.md"))->toContain('composer require acme/price-tracker')
        ->and($this->generated("{$root}/CHANGELOG.md"))->toContain('`acme/price-tracker`')
        ->and($this->generated("{$root}/LICENSE.md"))->toContain('The MIT License (MIT)')->toContain('Copyright (c) '.date('Y'))
        ->and($this->generated('lathe-components/README.md'))->toContain('lathe:plugin');
});

it('leaves no placeholder in any file', function () {
    $this->artisan('lathe:plugin', ['name' => 'acme/price-tracker', '--skip-composer' => true])->assertExitCode(0);

    foreach (File::allFiles("{$this->generatorBase}/lathe-components", true) as $file) {
        expect($file->getContents())->not->toContain('{{');
    }
});

it('requires a caret and the installed major of lathe/lathe on a release', function () {
    $this->app[Kernel::class]->registerCommand(new class extends MakePluginCommand
    {
        protected function installedVersion(): ?string
        {
            return '2.4.1.0';
        }
    });

    $this->artisan('lathe:plugin', ['name' => 'acme/price-tracker', '--skip-composer' => true])->assertExitCode(0);

    expect(json_decode($this->generated('lathe-components/PriceTracker/composer.json'), true)['require']['lathe/lathe'])->toBe('^2');
});

it('exits 1 when the installed version of lathe/lathe cannot be read', function () {
    $this->app[Kernel::class]->registerCommand(new class extends MakePluginCommand
    {
        protected function installedVersion(): ?string
        {
            return null;
        }
    });

    $this->artisan('lathe:plugin', ['name' => 'acme/price-tracker', '--skip-composer' => true])->assertExitCode(1);

    expect(File::exists("{$this->generatorBase}/lathe-components"))->toBeFalse();
});

it('runs composer update only after a yes', function (array $options, ?bool $answer, bool $runs) {
    $command = new class extends MakePluginCommand
    {
        /** @var list<string> */
        public static array $ran = [];

        protected function runComposerUpdate(string $package): void
        {
            self::$ran[] = $package;
        }
    };
    $command::$ran = [];
    $this->app[Kernel::class]->registerCommand($command);

    $pending = $this->artisan('lathe:plugin', ['name' => 'acme/price-tracker', ...$options]);

    if ($answer !== null) {
        $pending->expectsConfirmation('Run composer update acme/price-tracker now?', $answer ? 'yes' : 'no');
    }

    $pending->assertExitCode(0)->run();

    expect($command::$ran)->toBe($runs ? ['acme/price-tracker'] : []);
})->with([
    'yes' => [[], true, true],
    'no' => [[], false, false],
    'skip flag' => [['--skip-composer' => true], null, false],
    'no interaction' => [['--no-interaction' => true], null, false],
]);

it('writes into the Testbench working path when one is set, and into the app base path otherwise', function (bool $testbench) {
    $this->app->forgetInstance('lathe.host_path');
    $working = sys_get_temp_dir().'/lathe-working-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($working);
    File::put("{$working}/composer.json", '{}');
    $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($working));
    $previous = getenv('TESTBENCH_WORKING_PATH');
    putenv($testbench ? "TESTBENCH_WORKING_PATH={$working}" : 'TESTBENCH_WORKING_PATH');

    try {
        $this->artisan('lathe:plugin', ['name' => 'acme/price-tracker', '--skip-composer' => true])->assertExitCode(0);
    } finally {
        putenv($previous === false ? 'TESTBENCH_WORKING_PATH' : "TESTBENCH_WORKING_PATH={$previous}");
    }

    [$host, $other] = $testbench ? [$working, $this->generatorBase] : [$this->generatorBase, $working];

    expect(File::exists("{$host}/lathe-components/PriceTracker/composer.json"))->toBeTrue()
        ->and(File::exists("{$other}/lathe-components"))->toBeFalse();
})->with(['Testbench' => true, 'app' => false]);
