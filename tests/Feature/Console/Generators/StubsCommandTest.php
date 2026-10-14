<?php

use Illuminate\Support\Facades\File;
use Lathe\Tests\Feature\Console\Generators\GeneratesIntoTempApp;

uses(GeneratesIntoTempApp::class);

beforeEach(function () {
    $this->generatorApp();
    $this->stubs = dirname(__DIR__, 4).'/stubs';
});

it('publishes every top-level stub and not the plugin skeleton', function () {
    $this->artisan('lathe:stubs')->assertExitCode(0);

    $source = collect(File::files($this->stubs))->map->getFilename()->sort()->values()->all();
    $published = collect(File::files("{$this->generatorBase}/stubs/lathe"))->map->getFilename()->sort()->values()->all();

    expect($published)->toBe($source)
        ->toContain('plugin.stub', 'plugin-react.stub', 'LatheServiceProvider.stub', 'vite.lathe.config.stub', 'lathe-components-readme.md', 'widget-help.stub')
        ->and(File::exists("{$this->generatorBase}/stubs/lathe/plugin-skeleton"))->toBeFalse();
});

it('keeps an edited stub unless --force is set', function () {
    File::ensureDirectoryExists("{$this->generatorBase}/stubs/lathe");
    File::put("{$this->generatorBase}/stubs/lathe/action.stub", 'mine');

    $this->artisan('lathe:stubs')->assertExitCode(0);

    expect($this->generated('stubs/lathe/action.stub'))->toBe('mine');

    $this->artisan('lathe:stubs', ['--force' => true])->assertExitCode(0);

    expect($this->generated('stubs/lathe/action.stub'))->toBe(File::get("{$this->stubs}/action.stub"));
});

it('holds 37 top-level stubs and the plugin skeleton', function () {
    expect(File::isDirectory("{$this->stubs}/plugin-skeleton"))->toBeTrue()
        ->and(count(File::files($this->stubs)))->toBe(37);
});

it('imports every React stub from core and ui, never from a window global', function () {
    foreach (File::glob("{$this->stubs}/*-react.stub") as $path) {
        expect(File::get($path))->toContain("from '@lathephp/core'")->not->toContain('window.');
    }

    expect(File::get("{$this->stubs}/plugin-react.stub"))->toContain("from '@lathephp/ui'");
});
