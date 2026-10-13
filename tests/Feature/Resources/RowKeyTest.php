<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Lathe\Exceptions\DuplicateRowKeyException;
use Lathe\Lathe;
use Lathe\Tests\Fixtures\Models\Swatch as SwatchModel;
use Lathe\Tests\Fixtures\Resources\ColourKeyedSwatch;
use Lathe\Tests\Fixtures\Resources\Swatch;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => $user->email === 'admin@lathephp.com');
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    Schema::create('swatches', function (Blueprint $table) {
        $table->string('code')->primary();
        $table->string('colour');
        $table->timestamps();
    });
    Lathe::registerResources(Swatch::class, ColourKeyedSwatch::class);
    SwatchModel::forceCreate(['code' => 'sw-1', 'colour' => 'red']);
    SwatchModel::forceCreate(['code' => 'sw-2', 'colour' => 'red']);
});

it('keys rows by the model key name by default', function () {
    config()->set('app.debug', true);

    $table = $this->getJson('/lathe/api/resources/swatches')->assertOk()->json('table');

    expect(Swatch::rowKey())->toBe('code')
        ->and($table['rowKey'])->toBe('code')
        ->and(array_column($table['rows'], 'id'))->toBe(['sw-2', 'sw-1']);
});

it('throws when two rows on a page share a key and app.debug is on', function () {
    config()->set('app.debug', true);
    $this->withoutExceptionHandling();

    expect(fn () => $this->getJson('/lathe/api/resources/colour-keyed-swatches'))
        ->toThrow(DuplicateRowKeyException::class, 'Lathe\Tests\Fixtures\Resources\ColourKeyedSwatch has two rows with the colour key "red" on one page.');
});

it('logs an error and still lists the rows when two share a key and app.debug is off', function () {
    config()->set('app.debug', false);
    Log::spy();

    $table = $this->getJson('/lathe/api/resources/colour-keyed-swatches')->assertOk()->json('table');

    expect($table['rowKey'])->toBe('colour')
        ->and(array_column($table['rows'], 'id'))->toBe(['red', 'red']);
    Log::shouldHaveReceived('error')->once()->with('Lathe\Tests\Fixtures\Resources\ColourKeyedSwatch has two rows with the colour key "red" on one page.');
});
