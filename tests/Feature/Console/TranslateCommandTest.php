<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->lang = sys_get_temp_dir().'/lathe-translate-'.getmypid().'-'.uniqid();
    File::makeDirectory($this->lang);
    app()->useLangPath($this->lang);
});

afterEach(function () {
    File::deleteDirectory($this->lang);
});

it('writes the English catalogue for a new language', function () {
    $this->artisan('lathe:translate', ['language' => 'fr'])->assertExitCode(0);

    expect(File::get($this->lang.'/vendor/lathe/fr.json'))->toBe(File::get(__DIR__.'/../../../lang/en.json'));
});

it('exits 1 for an existing file without --force and keeps its content', function () {
    File::ensureDirectoryExists($this->lang.'/vendor/lathe');
    File::put($this->lang.'/vendor/lathe/fr.json', '{"Save": "Garder"}');

    $this->artisan('lathe:translate', ['language' => 'fr'])->assertExitCode(1);

    expect(File::get($this->lang.'/vendor/lathe/fr.json'))->toBe('{"Save": "Garder"}');
});

it('overwrites an existing file with --force', function () {
    File::ensureDirectoryExists($this->lang.'/vendor/lathe');
    File::put($this->lang.'/vendor/lathe/fr.json', '{"Save": "Garder"}');

    $this->artisan('lathe:translate', ['language' => 'fr', '--force' => true])->assertExitCode(0);

    expect(File::get($this->lang.'/vendor/lathe/fr.json'))->toBe(File::get(__DIR__.'/../../../lang/en.json'));
});
