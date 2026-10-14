<?php

use Illuminate\Support\Facades\File;
use Lathe\Tests\Feature\Console\Generators\GeneratesIntoTempApp;

uses(GeneratesIntoTempApp::class);

beforeEach(fn () => $this->generatorApp());

it('writes a class that parses and extends its Lathe base class', function (string $command, string $folder, string $extends) {
    $this->artisan($command, ['name' => 'Sample'])->assertExitCode(0);

    $code = $this->generated("app/Lathe/{$folder}/Sample.php");

    expect(fn () => token_get_all($code, TOKEN_PARSE))->not->toThrow(ParseError::class)
        ->and($code)->toContain("namespace App\\Lathe\\{$folder};\n")
        ->and($code)->toContain("class Sample extends {$extends}\n");
})->with([
    'resource' => ['lathe:resource', 'Resources', 'Resource'],
    'action' => ['lathe:action', 'Actions', 'Action'],
    'lens' => ['lathe:lens', 'Lenses', 'Lens'],
    'dashboard' => ['lathe:dashboard', 'Dashboards', 'Dashboard'],
    'filter' => ['lathe:filter', 'Filters', 'Filter'],
]);

it('writes the filter type given by --type', function (string $type, string $extends) {
    $this->artisan('lathe:filter', ['name' => 'Status', '--type' => $type])->assertExitCode(0);

    expect($this->generated('app/Lathe/Filters/Status.php'))->toContain("class Status extends {$extends}\n");
})->with([
    'select' => ['select', 'Filter'],
    'boolean' => ['boolean', 'BooleanFilter'],
    'date' => ['date', 'DateFilter'],
    'range' => ['range', 'RangeFilter'],
]);

it('writes a select filter without --type', function () {
    $this->artisan('lathe:filter', ['name' => 'Status'])->assertExitCode(0);

    expect($this->generated('app/Lathe/Filters/Status.php'))->toContain('use Lathe\Filters\Filter;');
});

it('refuses an unknown filter type', function () {
    $this->artisan('lathe:filter', ['name' => 'Status', '--type' => 'color'])->assertExitCode(1);

    expect(File::exists("{$this->generatorBase}/app/Lathe"))->toBeFalse();
});

it('overwrites a filter with --force', function () {
    $this->artisan('lathe:filter', ['name' => 'Status'])->assertExitCode(0);
    $this->artisan('lathe:filter', ['name' => 'Status'])->assertExitCode(1);
    $this->artisan('lathe:filter', ['name' => 'Status', '--type' => 'date', '--force' => true])->assertExitCode(0);

    expect($this->generated('app/Lathe/Filters/Status.php'))->toContain('extends DateFilter');
});
