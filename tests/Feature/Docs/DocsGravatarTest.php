<?php

use Illuminate\Support\Facades\File;

it('tells the reader once that a Gravatar field sends an email hash to Gravatar', function () {
    $docs = collect(File::allFiles(dirname(__DIR__, 3).'/docs'))
        ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'md')
        ->map(fn (SplFileInfo $file) => File::get($file->getPathname()))
        ->implode("\n");

    expect(substr_count($docs, 'the browser sends an email hash to Gravatar'))->toBe(1)
        ->and(File::get(dirname(__DIR__, 3).'/docs/your-first-resource/fields.md'))->toContain('the browser sends an email hash to Gravatar');
});
