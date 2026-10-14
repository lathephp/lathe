<?php

use Illuminate\Support\Facades\File;
use Lathe\Tests\Feature\Console\Generators\GeneratesIntoTempApp;

uses(GeneratesIntoTempApp::class);

beforeEach(fn () => $this->generatorApp());

it('writes the widget class of each --type', function (string $type, string $extends) {
    $this->artisan('lathe:widget', ['name' => 'TotalUsers', '--type' => $type])->assertExitCode(0);

    $code = $this->generated('app/Lathe/Widgets/TotalUsers.php');

    expect(fn () => token_get_all($code, TOKEN_PARSE))->not->toThrow(ParseError::class)
        ->and($code)->toContain("namespace App\\Lathe\\Widgets;\n")
        ->toContain("use Lathe\\Widgets\\{$extends};\n")
        ->toContain("class TotalUsers extends {$extends}\n")
        ->not->toContain('strict_types')
        ->not->toContain('final class');
})->with([
    'value' => ['value', 'ValueWidget'],
    'trend' => ['trend', 'TrendWidget'],
    'partition' => ['partition', 'PartitionWidget'],
    'progress' => ['progress', 'ProgressWidget'],
    'table' => ['table', 'TableWidget'],
    'custom' => ['custom', 'CustomWidget'],
    'bar' => ['bar', 'BarWidget'],
    'pie' => ['pie', 'PieWidget'],
    'radar' => ['radar', 'RadarWidget'],
    'radial' => ['radial', 'RadialWidget'],
    'help' => ['help', 'HelpWidget'],
]);

it('writes a value widget without --type and no React component', function () {
    $this->artisan('lathe:widget', ['name' => 'TotalUsers'])->assertExitCode(0);

    expect($this->generated('app/Lathe/Widgets/TotalUsers.php'))->toContain('extends ValueWidget')
        ->and(File::exists("{$this->generatorBase}/resources/js/lathe"))->toBeFalse();
});

it('refuses an unknown widget type', function () {
    $this->artisan('lathe:widget', ['name' => 'TotalUsers', '--type' => 'gauge'])->assertExitCode(1);

    expect(File::exists("{$this->generatorBase}/app/Lathe"))->toBeFalse();
});

it('writes a custom widget with its React component registered under the class key', function () {
    $this->artisan('lathe:widget', ['name' => 'TotalUsers', '--type' => 'custom'])->assertExitCode(0);

    expect($this->generated('app/Lathe/Widgets/TotalUsers.php'))->toContain("protected ?string \$component = 'total-users';")
        ->and($this->generated('resources/js/lathe/widgets/TotalUsers.tsx'))
        ->toContain("import { widget } from '@lathephp/core';")
        ->toContain("widget('total-users', TotalUsers);")
        ->not->toContain('window.')
        ->and($this->generated('resources/js/lathe/boot.ts'))->toContain("import './widgets/TotalUsers';");
});

it('extends the field type given by --extends', function (string $extends, string $base) {
    $this->artisan('lathe:field', ['name' => 'Color', '--extends' => $extends])->assertExitCode(0);

    $code = $this->generated('app/Lathe/Fields/Color.php');

    expect(fn () => token_get_all($code, TOKEN_PARSE))->not->toThrow(ParseError::class)
        ->and($code)->toContain("use Lathe\\Field\\{$base};\n")
        ->toContain("class Color extends {$base}\n")
        ->and(File::exists("{$this->generatorBase}/resources/js/lathe"))->toBeFalse();
})->with([
    'field' => ['field', 'Field'],
    'text' => ['text', 'TextField'],
    'textarea' => ['textarea', 'TextareaField'],
    'number' => ['number', 'NumberField'],
    'boolean' => ['boolean', 'BooleanField'],
    'date' => ['date', 'DateField'],
    'datetime' => ['datetime', 'DateTimeField'],
    'code' => ['code', 'CodeField'],
    'badge' => ['badge', 'BadgeField'],
    'status' => ['status', 'StatusField'],
    'file' => ['file', 'FileField'],
    'image' => ['image', 'ImageField'],
]);

it('refuses an unknown field type', function () {
    $this->artisan('lathe:field', ['name' => 'Color', '--extends' => 'hologram'])->assertExitCode(1);

    expect(File::exists("{$this->generatorBase}/app/Lathe"))->toBeFalse();
});

it('writes a field with --custom and a React component under the same key', function () {
    $this->artisan('lathe:field', ['name' => 'ColorPicker', '--custom' => true])->assertExitCode(0);

    expect($this->generated('app/Lathe/Fields/ColorPicker.php'))->toContain("protected string \$component = 'color-picker';")
        ->and($this->generated('resources/js/lathe/fields/ColorPicker.tsx'))
        ->toContain("import { field } from '@lathephp/core';")
        ->toContain("field('color-picker', ColorPicker);")
        ->and($this->generated('resources/js/lathe/boot.ts'))->toContain("import './fields/ColorPicker';");
});

it('writes a filter with --custom and a React component', function () {
    $this->artisan('lathe:filter', ['name' => 'Rating', '--custom' => true])->assertExitCode(0);

    $code = $this->generated('app/Lathe/Filters/Rating.php');

    expect(fn () => token_get_all($code, TOKEN_PARSE))->not->toThrow(ParseError::class)
        ->and($code)->toContain('class Rating extends Filter')->toContain("return 'rating-filter';")
        ->and($this->generated('resources/js/lathe/filters/Rating.tsx'))->toContain("filter('rating-filter', Rating);")
        ->and($this->generated('resources/js/lathe/boot.ts'))->toContain("import './filters/Rating';");
});

it('writes a page class and its React component', function () {
    $this->artisan('lathe:page', ['name' => 'SalesReport'])->assertExitCode(0);

    $code = $this->generated('app/Lathe/Pages/SalesReport.php');

    expect(fn () => token_get_all($code, TOKEN_PARSE))->not->toThrow(ParseError::class)
        ->and($code)->toContain("namespace App\\Lathe\\Pages;\n")
        ->toContain("class SalesReport extends Page\n")
        ->toContain("return 'Sales Report';")
        ->and($this->generated('resources/js/lathe/pages/SalesReport.tsx'))->toContain("page('sales-report', SalesReport);");
});

it('writes a resource plugin class and its React component', function () {
    $this->artisan('lathe:resource-plugin', ['name' => 'AuditTrail'])->assertExitCode(0);

    $code = $this->generated('app/Lathe/Plugins/AuditTrail.php');

    expect(fn () => token_get_all($code, TOKEN_PARSE))->not->toThrow(ParseError::class)
        ->and($code)->toContain("namespace App\\Lathe\\Plugins;\n")->toContain("class AuditTrail extends ResourcePlugin\n")
        ->and($this->generated('resources/js/lathe/plugins/AuditTrail.tsx'))->toContain("resourcePlugin('audit-trail', AuditTrail);");
});

it('writes a resource plugin into a plugin with --plugin and no React component', function () {
    File::ensureDirectoryExists("{$this->generatorBase}/lathe-components/Billing");
    File::put("{$this->generatorBase}/lathe-components/Billing/composer.json", json_encode(['autoload' => ['psr-4' => ['Acme\\Billing\\' => 'src/']]], JSON_UNESCAPED_SLASHES));

    $this->artisan('lathe:resource-plugin', ['name' => 'AuditTrail', '--plugin' => 'acme/billing'])->assertExitCode(0);

    expect($this->generated('lathe-components/Billing/src/Plugins/AuditTrail.php'))->toContain("namespace Acme\\Billing\\Plugins;\n")
        ->and(File::exists("{$this->generatorBase}/resources/js/lathe"))->toBeFalse();
});

it('exits 1 and names the missing name argument', function (string $command) {
    $this->artisan($command, ['--no-interaction' => true])
        ->expectsOutputToContain('name')
        ->assertExitCode(1);
})->with(['lathe:widget', 'lathe:field', 'lathe:page', 'lathe:resource-plugin', 'lathe:filter']);
