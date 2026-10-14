<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Lathe\Lathe;
use Lathe\Tests\Feature\Console\Generators\GeneratesIntoTempApp;

uses(GeneratesIntoTempApp::class);

/**
 * A temp app with an Invoice model under a namespace no other test uses, and the generated
 * files loaded from it, because the Doctor reads real classes.
 *
 * @return class-string<\Lathe\Resources\Resource>
 */
function loadGeneratedInvoice(object $test, bool $withPolicy): string
{
    $namespace = 'Probe'.bin2hex(random_bytes(3));
    $test->generatorApp("{$namespace}\\");
    File::ensureDirectoryExists("{$test->generatorBase}/app/Models");
    File::put("{$test->generatorBase}/app/Models/Invoice.php", "<?php\nnamespace {$namespace}\\Models;\nclass Invoice extends \\Illuminate\\Database\\Eloquent\\Model {}\n");

    $test->artisan('lathe:resource', ['name' => 'Invoice'])->assertExitCode(0);

    if (! $withPolicy) {
        File::delete("{$test->generatorBase}/app/Policies/InvoicePolicy.php");
    }

    require_once "{$test->generatorBase}/app/Models/Invoice.php";
    require_once "{$test->generatorBase}/app/Lathe/Resources/Invoice.php";

    if ($withPolicy) {
        require_once "{$test->generatorBase}/app/Policies/InvoicePolicy.php";
    }

    return "{$namespace}\\Lathe\\Resources\\Invoice";
}

it('writes a Resource and a Policy beside it', function () {
    $this->generatorApp();

    $this->artisan('lathe:resource', ['name' => 'Invoice'])->assertExitCode(0);

    expect($this->generated('app/Lathe/Resources/Invoice.php'))->toContain('->model(\App\Models\Invoice::class)')
        ->and($this->generated('app/Policies/InvoicePolicy.php'))
        ->toContain("namespace App\\Policies;\n")
        ->toContain("use App\\Models\\Invoice;\n")
        ->toContain('class InvoicePolicy');
});

it('leaves an existing Policy unchanged', function () {
    $this->generatorApp();
    File::ensureDirectoryExists("{$this->generatorBase}/app/Policies");
    File::put("{$this->generatorBase}/app/Policies/InvoicePolicy.php", '<?php // mine');

    $this->artisan('lathe:resource', ['name' => 'Invoice'])->assertExitCode(0);

    expect($this->generated('app/Policies/InvoicePolicy.php'))->toBe('<?php // mine');
});

it('writes no Policy for a plugin Resource', function () {
    $this->generatorApp();
    File::ensureDirectoryExists("{$this->generatorBase}/lathe-components/Billing");
    File::put("{$this->generatorBase}/lathe-components/Billing/composer.json", '{"autoload":{"psr-4":{"Acme\\\\Billing\\\\":"src/"}}}');

    $this->artisan('lathe:resource', ['name' => 'Invoice', '--plugin' => 'acme/billing'])->assertExitCode(0);

    expect(File::exists("{$this->generatorBase}/lathe-components/Billing/src/Resources/Invoice.php"))->toBeTrue()
        ->and(File::exists("{$this->generatorBase}/app/Policies"))->toBeFalse();
});

it('warns and exits 0 when the model class does not exist', function () {
    $this->generatorApp();

    $this->artisan('lathe:resource', ['name' => 'Invoice'])
        ->expectsOutputToContain('App\Models\Invoice does not exist')
        ->assertExitCode(0);
});

it('leaves the Doctor no warning about a generated Resource that has its Policy', function () {
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(loadGeneratedInvoice($this, withPolicy: true));

    Artisan::call('lathe:doctor');

    expect(Artisan::output())->not->toMatch('/WARN.*Invoice/');
});

it('warns about the same Resource without its Policy', function () {
    Gate::define('viewLathe', fn () => true);
    $resource = loadGeneratedInvoice($this, withPolicy: false);
    Lathe::registerResources($resource);

    Artisan::call('lathe:doctor');

    expect(Artisan::output())->toContain("WARN {$resource} has no policy");
});
