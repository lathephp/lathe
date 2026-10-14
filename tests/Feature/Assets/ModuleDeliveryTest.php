<?php

use Illuminate\Support\Facades\File;
use Lathe\Lathe;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/lathe-modules-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($this->dir);
    File::put("{$this->dir}/heartbeat.js", 'export const beat = 1;');
    File::put("{$this->dir}/heartbeat.css", 'body{color:red}');
    File::put("{$this->dir}/.env", 'SECRET=1');
    Lathe::script('heartbeat', "{$this->dir}/heartbeat.js");
    Lathe::style('heartbeat', "{$this->dir}/heartbeat.css");
});

afterEach(fn () => File::deleteDirectory($this->dir));

it('serves a registered style by its content hash, immutably', function () {
    $this->get('/lathe/styles/heartbeat-15c42ab7.css')
        ->assertOk()
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public')
        ->assertHeader('Content-Type', 'text/css; charset=utf-8')
        ->assertCookieMissing('laravel_session');
});

it('serves a registered script by its content hash, immutably', function () {
    $this->get('/lathe/modules/heartbeat-15dcd957.js')
        ->assertOk()
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public')
        ->assertHeader('Content-Type', 'text/javascript; charset=utf-8');
});

it('changes the URL when the file changes', function () {
    $this->get('/lathe/modules/heartbeat-15dcd957.js')->assertOk();

    File::put("{$this->dir}/heartbeat.js", 'export const beat = 2;');

    $this->get('/lathe/modules/heartbeat-15dcd957.js')->assertNotFound();
    $this->get('/lathe/modules/heartbeat-76945dcf.js')->assertOk();
    expect(Lathe::plugins()->moduleUrls())->toBe(['/lathe/modules/heartbeat-76945dcf.js']);
});

it('answers 404 for a wrong hash, an unregistered name, a climbing path or an absolute path', function (string $url) {
    $this->get($url)->assertNotFound();
})->with([
    'wrong hash' => '/lathe/modules/heartbeat-00000000.js',
    'unregistered name' => '/lathe/modules/other-15dcd957.js',
    'style wrong hash' => '/lathe/styles/heartbeat-00000000.css',
    'style unregistered name' => '/lathe/styles/other-15c42ab7.css',
    'climbing module' => '/lathe/modules/..%2f..%2f.env',
    'climbing style' => '/lathe/styles/..%2f..%2f.env',
    'absolute module' => '/lathe/modules/%2Fetc%2Fpasswd',
    'absolute style' => '/lathe/styles/%2Fetc%2Fpasswd',
    'script name on the style route' => '/lathe/styles/heartbeat-15dcd957.js',
]);
