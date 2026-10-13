<?php

use Symfony\Component\Process\Process;

// It reads the built manifest, so it needs Node and a build.
pest()->group('frontend');

beforeEach(fn () => holdBuildLock());

it('keeps the Tiptap, Markdown and code editors out of every route\'s first load', function () {
    $root = dirname(__DIR__, 3);

    if (! is_file("{$root}/dist/manifest.json")) {
        (new Process(['pnpm', 'build'], $root, timeout: 600))->mustRun();
    }

    $process = new Process(['node', 'scripts/size.mjs', 'dist', '--json'], $root, timeout: 120);
    $process->mustRun();
    $routes = json_decode($process->getOutput(), true);
    $manifest = json_decode((string) file_get_contents("{$root}/dist/manifest.json"), true);
    $editors = array_values(array_filter(array_map(
        fn (array $chunk) => $chunk['file'],
        $manifest,
    ), fn (string $file) => preg_match('#/(TiptapEditor|MarkdownEditor|CodeEditor)-#', $file) === 1));

    expect($editors)->toHaveCount(3)
        ->and(array_column($routes, 'route'))->toContain('/lathe/resources/orders/new');

    foreach ($routes as $route) {
        expect(array_intersect($route['files'], $editors))->toBe([]);
    }
});

it('gives rendered rich text the prose typography', function () {
    $root = dirname(__DIR__, 3);
    $css = implode("\n", array_map(fn (string $file) => (string) file_get_contents($file), glob("{$root}/dist/assets/*.css") ?: []));

    expect($css)->toContain('.prose{')
        ->and((string) file_get_contents("{$root}/resources/js/fields/builtin/rich.tsx"))->toContain('prose');
});
