<?php

use Illuminate\Support\Facades\File;
use Lathe\Tests\Feature\Console\Generators\GeneratesIntoTempApp;

uses(GeneratesIntoTempApp::class);

beforeEach(fn () => $this->generatorApp());

it('writes a snake case name as a StudlyCase class in the Actions folder with the app namespace', function () {
    $this->artisan('lathe:action', ['name' => 'archive_order'])->assertExitCode(0);

    $code = $this->generated('app/Lathe/Actions/ArchiveOrder.php');

    expect($code)
        ->toContain("namespace App\\Lathe\\Actions;\n")
        ->toContain('class ArchiveOrder extends Action');
});

it('takes the namespace from the app', function () {
    $this->generatorApp('Acme\\Shop\\');

    $this->artisan('lathe:action', ['name' => 'Archive'])->assertExitCode(0);

    expect($this->generated('app/Lathe/Actions/Archive.php'))->toContain("namespace Acme\\Shop\\Lathe\\Actions;\n");
});

it('puts a slashed name in a sub namespace and folder', function () {
    $this->artisan('lathe:action', ['name' => 'Billing/Refund'])->assertExitCode(0);

    expect($this->generated('app/Lathe/Actions/Billing/Refund.php'))
        ->toContain("namespace App\\Lathe\\Actions\\Billing;\n")
        ->toContain('class Refund extends Action');
});

it('refuses a name outside letters, digits, underscore and slash', function (string $name) {
    $this->artisan('lathe:action', ['name' => $name])->assertExitCode(1);

    expect(File::exists($this->generatorBase.'/app/Lathe'))->toBeFalse();
})->with(['Bad Name!', 'Bad-Name', 'Back\\Slash', '9Lives', 'Billing//Refund', 'Billing/']);

it('refuses a reserved name', function (string $name) {
    $this->artisan('lathe:action', ['name' => $name])->assertExitCode(1);

    expect(File::exists($this->generatorBase.'/app/Lathe'))->toBeFalse();
})->with(['Action', 'Dashboard', 'Field', 'Filter', 'Lens', 'Plugin', 'Resource', 'Widget']);

it('exits 1 and leaves an existing class unchanged', function () {
    $this->artisan('lathe:action', ['name' => 'Archive'])->assertExitCode(0);
    $path = "{$this->generatorBase}/app/Lathe/Actions/Archive.php";
    File::put($path, '<?php // edited');

    $this->artisan('lathe:action', ['name' => 'Archive'])->assertExitCode(1);

    expect(File::get($path))->toBe('<?php // edited');
});

it('overwrites an existing class with --force', function () {
    $this->artisan('lathe:action', ['name' => 'Archive'])->assertExitCode(0);
    $path = "{$this->generatorBase}/app/Lathe/Actions/Archive.php";
    File::put($path, '<?php // edited');

    $this->artisan('lathe:action', ['name' => 'Archive', '--force' => true])->assertExitCode(0);

    expect(File::get($path))->toContain('class Archive extends Action');
});

it('uses a stub published to stubs/lathe instead of the package stub', function () {
    File::ensureDirectoryExists("{$this->generatorBase}/stubs/lathe");
    File::put("{$this->generatorBase}/stubs/lathe/action.stub", "<?php // mine {{ class }} in {{ namespace }}\n");

    $this->artisan('lathe:action', ['name' => 'Archive'])->assertExitCode(0);

    expect($this->generated('app/Lathe/Actions/Archive.php'))->toBe("<?php // mine Archive in App\\Lathe\\Actions\n");
});

it('exits 1 and names the missing argument under --no-interaction', function () {
    $this->artisan('lathe:action', ['--no-interaction' => true])
        ->expectsOutputToContain('name')
        ->assertExitCode(1);
});

it('renders PHP in Laravel style, without strict types or final', function (string $command) {
    $this->artisan($command, ['name' => 'Sample'])->assertExitCode(0);

    $code = File::get(File::allFiles("{$this->generatorBase}/app/Lathe")[0]->getPathname());

    expect($code)->not->toContain('strict_types')->not->toContain('final class');
})->with(['lathe:resource', 'lathe:action', 'lathe:filter', 'lathe:lens', 'lathe:dashboard']);
