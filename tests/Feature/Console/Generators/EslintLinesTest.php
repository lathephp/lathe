<?php

use Illuminate\Support\Facades\File;
use Lathe\Tests\Feature\Console\Generators\GeneratesIntoTempApp;

uses(GeneratesIntoTempApp::class);

beforeEach(fn () => $this->generatorApp());

const LATHE_ESLINT_IMPORT = "import lathe from '@lathephp/vite-plugin/eslint';";

it('prints the two ESLint lines on the first React component and leaves the app config alone', function (string $command, array $arguments) {
    $config = "{$this->generatorBase}/eslint.config.js";
    File::put($config, "export default [];\n");

    $this->artisan($command, $arguments)
        ->expectsOutputToContain(LATHE_ESLINT_IMPORT)
        ->expectsOutputToContain('...lathe')
        ->assertExitCode(0);

    expect(File::get($config))->toBe("export default [];\n");
})->with([
    'a page' => ['lathe:page', ['name' => 'Reports']],
    'a field' => ['lathe:field', ['name' => 'Rating', '--custom' => true]],
    'a widget' => ['lathe:widget', ['name' => 'Clock', '--type' => 'custom']],
    'a plugin' => ['lathe:plugin', ['name' => 'Billing', '--inline' => true]],
]);

it('prints them only once, not on the components after the first', function () {
    $this->artisan('lathe:page', ['name' => 'Reports'])->assertExitCode(0);

    $this->artisan('lathe:page', ['name' => 'Sales'])
        ->doesntExpectOutputToContain(LATHE_ESLINT_IMPORT)
        ->assertExitCode(0);
});
