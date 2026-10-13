<?php

use Illuminate\Support\Facades\Schema;

it('creates the Lathe tables when the host migrates', function () {
    $this->artisan('migrate')->assertSuccessful();

    expect(Schema::hasTable('lathe_bulk_runs'))->toBeTrue()
        ->and(Schema::hasTable('lathe_plugin_settings'))->toBeTrue();
});

it('publishes the migrations with their fixed stamps', function () {
    // Publish into a private folder: the skeleton's own is shared with parallel test processes.
    $path = sys_get_temp_dir().'/lathe-publish-'.bin2hex(random_bytes(4));
    app()->useDatabasePath($path);
    (new Lathe\LatheServiceProvider(app()))->boot();

    $this->artisan('vendor:publish', ['--tag' => 'lathe-migrations', '--force' => true])->assertSuccessful();

    expect(glob("{$path}/migrations/2026_01_01_0000*_create_*.php"))->toHaveCount(10);

    Illuminate\Support\Facades\File::deleteDirectory($path);
});
