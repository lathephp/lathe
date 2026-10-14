<?php

use Symfony\Component\Yaml\Yaml;

/**
 * @return array<string, array{string}>
 */
function docsPages(): array
{
    $root = dirname(__DIR__, 3).'/docs';
    $pages = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'md') {
            $relative = substr($file->getPathname(), strlen($root) + 1);
            $pages[$relative] = [$file->getPathname()];
        }
    }

    ksort($pages);

    return $pages;
}

it('gives each docs page a title, a description and an order', function (string $path) {
    $text = (string) file_get_contents($path);

    expect($text)->toMatch('/\A---\n.*?\n---\n/s');
    preg_match('/\A---\n(.*?)\n---\n/s', $text, $match);
    $front = Yaml::parse($match[1]);

    expect($front)->toBeArray()
        ->and($front['title'] ?? null)->toBeString()->not->toBeEmpty()
        ->and($front['description'] ?? null)->toBeString()->not->toBeEmpty()
        ->and($front['order'] ?? null)->toBeInt();
})->with(docsPages());

it('ends each docs page with what the reader sees', function (string $path) {
    preg_match_all('/^## (.+)$/m', (string) preg_replace('/^```.*?^```/ms', '', (string) file_get_contents($path)), $headings);

    expect(end($headings[1]))->toBe('What you see');
})->with(docsPages());
