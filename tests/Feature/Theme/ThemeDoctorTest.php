<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Lathe\Lathe;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    Gate::define('viewLathe', fn () => true);

    // The doctor fails without a build, so a pair warning can only be told apart from that with a manifest.
    $this->dist = sys_get_temp_dir().'/lathe-dist-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists("{$this->dist}/assets");
    File::put("{$this->dist}/assets/app-abc12345.js", '');
    File::put("{$this->dist}/manifest.json", json_encode(['resources/js/app.tsx' => ['file' => 'assets/app-abc12345.js']]));
    Lathe::assets()->useDistPath($this->dist);
});

afterEach(function () {
    File::deleteDirectory($this->dist);
    Lathe::resources()->clearCache();
});

it('fails lathe:doctor on an unknown colour token', function () {
    config(['lathe.theme.colors.sparkle' => '#ff00ff']);

    $code = Artisan::call('lathe:doctor');
    $lines = explode("\n", Artisan::output());

    expect($code)->toBe(1)
        ->and($lines)->toContain('FAIL config/lathe.php: theme.colors.sparkle is not a colour token. Remove it or use a token such as primary.');
});

it('warns when an override pair is not readable and still exits 0', function () {
    config(['lathe.theme.colors.primary' => '#336699', 'lathe.theme.colors.primary-foreground' => '#336699']);

    $code = Artisan::call('lathe:doctor');
    $lines = explode("\n", Artisan::output());

    expect($code)->toBe(0)
        ->and($lines)->toContain('WARN config/lathe.php: theme.colors.primary-foreground on primary is 1.00:1 in light mode, under 4.5:1. Pick colours that differ more.');
});

it('warns about the dark half of a pair only', function () {
    config([
        'lathe.theme.colors.ring' => ['light' => '#000000', 'dark' => '#111111'],
        'lathe.theme.colors.background' => ['light' => '#ffffff', 'dark' => '#000000'],
    ]);

    Artisan::call('lathe:doctor');
    $output = Artisan::output();

    expect($output)->toContain('theme.colors.ring on background is 1.')
        ->and($output)->toContain('in dark mode, under 3:1')
        ->and($output)->not->toContain('in light mode, under 3:1');
});

it('stays quiet when an override pair is readable', function () {
    config(['lathe.theme.colors.primary' => '#000000', 'lathe.theme.colors.primary-foreground' => '#ffffff']);

    Artisan::call('lathe:doctor');

    expect(Artisan::output())->not->toContain('under 4.5:1');
});
