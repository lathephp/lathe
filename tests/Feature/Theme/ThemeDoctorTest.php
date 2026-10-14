<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    Gate::define('viewLathe', fn () => true);
});

it('fails lathe:doctor on an unknown colour token', function () {
    config(['lathe.theme.colors.sparkle' => '#ff00ff']);

    $code = Artisan::call('lathe:doctor');
    $lines = explode("\n", Artisan::output());

    expect($code)->toBe(1)
        ->and($lines)->toContain('FAIL config/lathe.php: theme.colors.sparkle is not a colour token. Remove it or use a token such as primary.');
});
