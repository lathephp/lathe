<?php

use Illuminate\Support\Facades\File;
use Lathe\Tests\Feature\Console\Generators\GeneratesIntoTempApp;

uses(GeneratesIntoTempApp::class);

beforeEach(function () {
    $this->generatorApp();
    File::put("{$this->generatorBase}/package.json", json_encode(['devDependencies' => ['vite' => '^7.0.0']]));
});

it('writes an in-app plugin class, a React page and a line in boot.ts', function () {
    $this->artisan('lathe:plugin', ['name' => 'Insights', '--inline' => true])->assertExitCode(0);

    expect($this->generated('app/Lathe/Plugins/Insights.php'))->toContain("namespace App\\Lathe\\Plugins;\n")
        ->toContain('class Insights extends Plugin')
        ->toContain('MenuItem::plugin(static::class)')
        ->and($this->generated('resources/js/lathe/plugins/Insights.tsx'))
        ->toContain("import { plugin } from '@lathephp/core';")
        ->toContain("import { Card } from '@lathephp/ui';")
        ->toContain("plugin('insights', Insights);")
        ->and($this->generated('resources/js/lathe/boot.ts'))->toContain("import './plugins/Insights';\n")
        ->and(File::exists("{$this->generatorBase}/vite.lathe.config.ts"))->toBeTrue()
        ->and(File::exists("{$this->generatorBase}/lathe-components"))->toBeFalse();
});

it('takes the namespace from the app and accepts a vendor/name pair', function () {
    $this->artisan('lathe:plugin', ['name' => 'acme/maps', '--inline' => true])->assertExitCode(0);

    expect($this->generated('app/Lathe/Plugins/Acme/Maps.php'))->toContain("namespace App\\Lathe\\Plugins\\Acme;\n")
        ->and($this->generated('resources/js/lathe/plugins/Maps.tsx'))->toContain("plugin('maps', Maps);");
});

it('refuses to overwrite an inline plugin without --force', function () {
    $this->artisan('lathe:plugin', ['name' => 'Insights', '--inline' => true])->assertExitCode(0);
    $this->artisan('lathe:plugin', ['name' => 'Insights', '--inline' => true])->assertExitCode(1);
    $this->artisan('lathe:plugin', ['name' => 'Insights', '--inline' => true, '--force' => true])->assertExitCode(0);
});
