<?php

use Illuminate\Support\Facades\File;

/*
 * The deployed demo copies the workbench build into the app's public folder, where Lathe
 * serves it. The workbench provider registers the same build itself for local use, so
 * the login page would load the boot script twice unless the provider steps aside.
 */

afterEach(function () {
    File::deleteDirectory(public_path('vendor/lathe/extensions'));
});

/**
 * The extension module URLs the login page lists, without the heartbeat module.
 *
 * @return list<string>
 */
function loginModules($test): array
{
    preg_match('#<script type="application/json" id="lathe-modules"[^>]*>(.*?)</script>#s', $test->get('/lathe/login')->assertOk()->getContent(), $found);

    return array_values(array_filter(json_decode($found[1], true), fn (string $url) => ! str_contains($url, 'heartbeat')));
}

it('loads the boot script once when the app serves the build from its public folder', function () {
    File::ensureDirectoryExists(public_path('vendor/lathe/extensions'));
    File::put(public_path('vendor/lathe/extensions/manifest.json'), '{"entry":"boot-test.js"}');
    $this->refreshApplication();

    expect(loginModules($this))->toBe(['/vendor/lathe/extensions/boot-test.js']);
})->group('demo');

it('registers the committed build itself when the app serves none', function () {
    $modules = loginModules($this);

    expect($modules)->toHaveCount(1)
        ->and($modules[0])->toStartWith('/lathe/modules/workbench-extensions-');
})->group('demo');
