<?php

use Illuminate\Support\Facades\File;
use Lathe\Tests\Feature\Console\Generators\GeneratesIntoTempApp;

uses(GeneratesIntoTempApp::class);

beforeEach(function () {
    $this->generatorApp();
    $this->plugin = function (string $folder, string $namespace = 'Acme\\Billing\\', string $src = 'src/') {
        File::ensureDirectoryExists("{$this->generatorBase}/{$folder}");
        File::put("{$this->generatorBase}/{$folder}/composer.json", json_encode(['autoload' => ['psr-4' => [$namespace => $src]]], JSON_UNESCAPED_SLASHES));
    };
});

it('writes into the src folder of a plugin in lathe-components', function (string $command, string $folder) {
    ($this->plugin)('lathe-components/Billing');

    $this->artisan($command, ['name' => 'Sample', '--plugin' => 'acme/billing'])->assertExitCode(0);

    expect($this->generated("lathe-components/Billing/src/{$folder}/Sample.php"))->toContain("namespace Acme\\Billing\\{$folder};\n")
        ->and(File::exists("{$this->generatorBase}/app/Lathe"))->toBeFalse();
})->with([
    'resource' => ['lathe:resource', 'Resources'],
    'action' => ['lathe:action', 'Actions'],
    'lens' => ['lathe:lens', 'Lenses'],
    'dashboard' => ['lathe:dashboard', 'Dashboards'],
]);

it('falls back to the plugin in vendor', function () {
    ($this->plugin)('vendor/acme/billing', 'Acme\\Billing\\');

    $this->artisan('lathe:action', ['name' => 'Refund', '--plugin' => 'acme/billing'])->assertExitCode(0);

    expect($this->generated('vendor/acme/billing/src/Actions/Refund.php'))->toContain("namespace Acme\\Billing\\Actions;\n");
});

it('prefers lathe-components over vendor', function () {
    ($this->plugin)('lathe-components/Billing', 'Local\\Billing\\');
    ($this->plugin)('vendor/acme/billing', 'Acme\\Billing\\');

    $this->artisan('lathe:action', ['name' => 'Refund', '--plugin' => 'acme/billing'])->assertExitCode(0);

    expect($this->generated('lathe-components/Billing/src/Actions/Refund.php'))->toContain("namespace Local\\Billing\\Actions;\n");
});

it('exits 1 and prints both searched paths when no plugin has a src root', function () {
    ($this->plugin)('lathe-components/Billing', 'Acme\\Billing\\', 'lib/');

    $this->artisan('lathe:action', ['name' => 'Refund', '--plugin' => 'acme/billing'])
        ->expectsOutputToContain("{$this->generatorBase}/lathe-components/Billing")
        ->expectsOutputToContain("{$this->generatorBase}/vendor/acme/billing")
        ->assertExitCode(1);
});

it('exits 1 for a plugin that is not given as vendor/name', function () {
    $this->artisan('lathe:action', ['name' => 'Refund', '--plugin' => 'billing'])->assertExitCode(1);
});
