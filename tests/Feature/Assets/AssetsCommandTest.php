<?php

use Illuminate\Support\Facades\File;
use Lathe\Assets\AssetRegistry;
use Lathe\Lathe;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/lathe-assets-cmd-'.bin2hex(random_bytes(4));
    $this->public = "{$this->dir}/public";
    File::ensureDirectoryExists($this->public);
    File::put("{$this->dir}/tool.js", 'export const beat = 1;');
    File::put("{$this->dir}/theme.css", 'body{color:red}');
    app()->usePublicPath($this->public);
    Lathe::script('tool', "{$this->dir}/tool.js");
    Lathe::style('theme', "{$this->dir}/theme.css");
});

afterEach(fn () => File::deleteDirectory($this->dir));

it('copies each registered script and style with the same bytes, and points the URLs there', function () {
    $this->artisan('lathe:assets')->assertSuccessful();

    expect(hash_file('sha256', "{$this->public}/vendor/lathe/plugins/tool.js"))->toBe(hash_file('sha256', "{$this->dir}/tool.js"))
        ->and(hash_file('sha256', "{$this->public}/vendor/lathe/plugins/theme.css"))->toBe(hash_file('sha256', "{$this->dir}/theme.css"))
        ->and(Lathe::plugins()->moduleUrls())->toContain('/vendor/lathe/plugins/tool.js?v=15dcd957');
    $this->get('/lathe/login')->assertSee('<link rel="stylesheet" href="/vendor/lathe/plugins/theme.css?v=15c42ab7"', false);
});

it('skips a remote asset', function () {
    app(AssetRegistry::class)->remoteScript('cdn', 'https://cdn.example.test/a.js');
    app(AssetRegistry::class)->remoteStyle('cdn', 'https://cdn.example.test/a.css');

    $this->artisan('lathe:assets')->assertSuccessful();

    expect(File::files("{$this->public}/vendor/lathe/plugins"))->toHaveCount(2)
        ->and(Lathe::plugins()->moduleUrls())->toContain('https://cdn.example.test/a.js');
});

it('warns about a missing source and still succeeds', function () {
    Lathe::script('gone', "{$this->dir}/gone.js");

    $this->artisan('lathe:assets')
        ->expectsOutputToContain('gone.js')
        ->assertSuccessful();

    expect(File::exists("{$this->public}/vendor/lathe/plugins/gone.js"))->toBeFalse()
        ->and(File::exists("{$this->public}/vendor/lathe/plugins/tool.js"))->toBeTrue();
});

it('keeps an existing copy unless --force is given', function () {
    $this->artisan('lathe:assets')->assertSuccessful();
    File::put("{$this->public}/vendor/lathe/plugins/tool.js", 'edited by hand');

    $this->artisan('lathe:assets')->assertSuccessful();
    expect(File::get("{$this->public}/vendor/lathe/plugins/tool.js"))->toBe('edited by hand');

    $this->artisan('lathe:assets', ['--force' => true])->assertSuccessful();
    expect(File::get("{$this->public}/vendor/lathe/plugins/tool.js"))->toBe('export const beat = 1;');
});
