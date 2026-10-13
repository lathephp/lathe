<?php

use Illuminate\Support\Facades\Schema;

it('creates the Lathe tables when the host migrates', function () {
    $this->artisan('migrate')->assertSuccessful();

    expect(Schema::hasTable('lathe_bulk_runs'))->toBeTrue()
        ->and(Schema::hasTable('lathe_plugin_settings'))->toBeTrue();
});

it('publishes the migrations with their fixed stamps', function () {
    $this->artisan('vendor:publish', ['--tag' => 'lathe-migrations', '--force' => true])->assertSuccessful();

    expect(glob(database_path('migrations/2026_01_01_0000*_create_*.php')))->toHaveCount(10);

    array_map(unlink(...), glob(database_path('migrations/2026_01_01_0000*_create_*.php')) ?: []);
});
