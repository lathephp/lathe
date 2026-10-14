<?php

use Illuminate\Support\Facades\File;
use Lathe\Tests\Feature\Console\Generators\GeneratesIntoTempApp;

uses(GeneratesIntoTempApp::class);

beforeEach(function () {
    $this->generatorApp();
    File::put("{$this->generatorBase}/vite.config.ts", "export default {};\n");
    File::put("{$this->generatorBase}/package.json", json_encode(['devDependencies' => ['vite' => '^7.0.0']]));
});

it('sets up the frontend once, beside a host vite config it leaves alone', function () {
    $before = hash_file('sha256', "{$this->generatorBase}/vite.config.ts");

    $this->artisan('lathe:widget', ['name' => 'TotalUsers', '--type' => 'custom'])->assertExitCode(0);

    $package = json_decode($this->generated('package.json'), true);

    expect(File::exists("{$this->generatorBase}/resources/js/lathe/boot.ts"))->toBeTrue()
        ->and(File::isDirectory("{$this->generatorBase}/resources/js/lathe/fields"))->toBeTrue()
        ->and(File::isDirectory("{$this->generatorBase}/resources/js/lathe/filters"))->toBeTrue()
        ->and(File::isDirectory("{$this->generatorBase}/resources/js/lathe/widgets"))->toBeTrue()
        ->and(File::isDirectory("{$this->generatorBase}/resources/js/lathe/plugins"))->toBeTrue()
        ->and($this->generated('vite.lathe.config.ts'))->toContain("lathe({ mode: 'app' })")->toContain("from '@lathephp/vite-plugin'")
        ->and($package['devDependencies'])->toMatchArray([
            'vite' => '^7.0.0',
            '@lathephp/core' => '1.0.0-dev',
            '@lathephp/ui' => '1.0.0-dev',
            '@lathephp/vite-plugin' => '1.0.0-dev',
        ])
        ->and(hash_file('sha256', "{$this->generatorBase}/vite.config.ts"))->toBe($before);
});

it('adds the lathe:build and lathe:dev scripts and keeps a script the app already has', function () {
    File::put("{$this->generatorBase}/package.json", json_encode(['scripts' => ['dev' => 'vite', 'lathe:dev' => 'my own'], 'devDependencies' => ['vite' => '^7.0.0']]));

    $this->artisan('lathe:widget', ['name' => 'TotalUsers', '--type' => 'custom'])->assertExitCode(0);

    expect(json_decode($this->generated('package.json'), true)['scripts'])->toBe([
        'dev' => 'vite',
        'lathe:build' => 'vite build --config vite.lathe.config.ts',
        'lathe:dev' => 'my own',
    ]);
});

it('registers each component in boot.ts and sets up only once', function () {
    $this->artisan('lathe:widget', ['name' => 'TotalUsers', '--type' => 'custom'])->assertExitCode(0);

    File::put("{$this->generatorBase}/vite.lathe.config.ts", "// edited\n");

    $this->artisan('lathe:page', ['name' => 'Reports'])
        ->doesntExpectOutputToContain('install')
        ->assertExitCode(0);

    expect($this->generated('resources/js/lathe/boot.ts'))
        ->toContain("import './widgets/TotalUsers';")
        ->toContain("import './pages/Reports';")
        ->and($this->generated('vite.lathe.config.ts'))->toBe("// edited\n");
});

it('prints the install command of the lock file found', function (?string $lock, string $command) {
    if ($lock !== null) {
        File::put("{$this->generatorBase}/{$lock}", '');
    }

    $this->artisan('lathe:widget', ['name' => 'TotalUsers', '--type' => 'custom'])
        ->expectsOutputToContain($command)
        ->assertExitCode(0);
})->with([
    'pnpm' => ['pnpm-lock.yaml', 'pnpm install'],
    'npm' => ['package-lock.json', 'npm install'],
    'yarn' => ['yarn.lock', 'yarn install'],
    'bun' => ['bun.lock', 'bun install'],
    'none' => [null, 'npm install'],
]);
