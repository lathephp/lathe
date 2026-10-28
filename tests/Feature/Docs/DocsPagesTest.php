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

// The rewritten pages keep the file names of the pages they replace, so each diffs against its
// source; tenancy, permission-plugin, support and limits-and-performance are new.
it('holds every docs page and no other', function () {
    expect(array_keys(docsPages()))->toBe([
        'extending/events.md',
        'extending/extension-api.md',
        'extending/frontend.md',
        'extending/icons.md',
        'extending/localization.md',
        'extending/notifications.md',
        'extending/pages.md',
        'extending/permission-plugin.md',
        'extending/plugin-distribution.md',
        'extending/plugins.md',
        'extending/stubs.md',
        'getting-started/changelog.md',
        'getting-started/commands.md',
        'getting-started/configuration.md',
        'getting-started/deployment.md',
        'getting-started/installation.md',
        'getting-started/limits-and-performance.md',
        'getting-started/quickstart.md',
        'getting-started/support.md',
        'getting-started/upgrade.md',
        'organizing/breadcrumbs.md',
        'organizing/dashboards.md',
        'organizing/layout.md',
        'organizing/menus.md',
        'organizing/widgets.md',
        'querying/board.md',
        'querying/filter-bar.md',
        'querying/filters.md',
        'querying/lenses.md',
        'querying/preview-panel.md',
        'querying/row-grouping.md',
        'querying/saved-tabs.md',
        'querying/search.md',
        'querying/workspace.md',
        'security/authentication.md',
        'security/impersonation.md',
        'security/policies.md',
        'security/tenancy.md',
        'taking-action/actions.md',
        'taking-action/audit-log.md',
        'taking-action/import-export.md',
        'your-first-resource/column-summaries.md',
        'your-first-resource/defining-resources.md',
        'your-first-resource/fields.md',
        'your-first-resource/relationships.md',
        'your-first-resource/status-meters.md',
        'your-first-resource/testing.md',
        'your-first-resource/validation.md',
    ]);
});
