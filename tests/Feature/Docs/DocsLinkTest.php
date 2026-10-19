<?php

use Illuminate\Support\Facades\File;

/**
 * The page's headings as GitHub-style anchors.
 *
 * @return list<string>
 */
function docsAnchors(string $path): array
{
    preg_match_all('/^#{1,6} (.+)$/m', (string) preg_replace('/^```.*?^```/ms', '', File::get($path)), $headings);

    // GitHub's rule: lower case, drop punctuation but hyphens and underscores, spaces become hyphens.
    return array_map(fn (string $heading) => str_replace(' ', '-', (string) preg_replace('/[^a-z0-9 _-]/', '', strtolower(trim($heading)))), $headings[1]);
}

it('resolves every relative link in the docs', function () {
    $root = dirname(__DIR__, 3);
    $broken = [];
    $checked = 0;

    foreach (File::allFiles("{$root}/docs") as $file) {
        if ($file->getExtension() !== 'md') {
            continue;
        }

        $text = (string) preg_replace('/^```.*?^```/ms', '', File::get($file->getPathname()));
        preg_match_all('/\]\(([^)\s]+)\)/', $text, $links);

        foreach ($links[1] as $link) {
            // lathephp.com serves /images/docs/ from its own public folder, and its link check covers them.
            if (preg_match('#^(https?:|mailto:|/images/docs/)#', $link) === 1) {
                continue;
            }

            $checked++;
            [$target, $anchor] = array_pad(explode('#', $link, 2), 2, null);
            $path = $target === '' ? $file->getPathname() : realpath($file->getPath().'/'.$target);

            if ($path === false || ! is_file($path)) {
                $broken[] = $file->getRelativePathname()." -> {$link}";
            } elseif ($anchor !== null && ! in_array($anchor, docsAnchors($path), true)) {
                $broken[] = $file->getRelativePathname()." -> {$link} (no such heading)";
            }
        }
    }

    expect($checked)->toBeGreaterThan(50)
        ->and($broken)->toBe([]);
});
