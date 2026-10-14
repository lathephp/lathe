<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use Lathe\Assets\RootTags;
use Lathe\Lathe;

beforeEach(function () {
    $this->public = sys_get_temp_dir().'/lathe-extensions-'.bin2hex(random_bytes(4));
    $this->extensions = "{$this->public}/vendor/lathe/extensions";
    File::ensureDirectoryExists($this->extensions);
    $this->app->usePublicPath($this->public);
});

afterEach(fn () => File::deleteDirectory($this->public));

/**
 * The modules the page loads, less the plugin modules the workbench registers.
 *
 * @return list<string>
 */
function pageModules(): array
{
    preg_match('#<script type="application/json" id="lathe-modules"[^>]*>(.*?)</script>#', app(RootTags::class)->scripts(), $match);

    return array_values(array_diff(json_decode($match[1] ?? '[]', true), Lathe::plugins()->moduleUrls()));
}

it('pins an empty module list when the extensions were never built', function () {
    expect(pageModules())->toBe([]);
});

it('lists the entry of the built extensions manifest as a module', function () {
    File::put("{$this->extensions}/manifest.json", json_encode(['entry' => 'boot-3f9a1c2e.js', 'components' => ['widget' => ['clock']]]));

    expect(pageModules())->toBe(['/vendor/lathe/extensions/boot-3f9a1c2e.js']);
});

it('lists the dev server URL from the hot file instead of the built entry', function () {
    File::put("{$this->extensions}/manifest.json", json_encode(['entry' => 'boot-3f9a1c2e.js', 'components' => []]));
    File::put("{$this->extensions}/hot", "http://localhost:5174/resources/js/lathe/boot.ts\n");

    expect(pageModules())->toBe(['http://localhost:5174/resources/js/lathe/boot.ts']);
});

it('links the stylesheets of the built extensions manifest with the CSP nonce', function () {
    Vite::useCspNonce('n0nce');
    File::put("{$this->extensions}/manifest.json", json_encode(['entry' => 'boot-3f9a1c2e.js', 'styles' => ['boot-7d0c4b19.css'], 'components' => []]));

    expect(app(RootTags::class)->styles())->toContain('<link rel="stylesheet" href="/vendor/lathe/extensions/boot-7d0c4b19.css" nonce="n0nce">');
});
