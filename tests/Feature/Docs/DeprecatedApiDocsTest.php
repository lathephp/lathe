<?php

use Illuminate\Support\Facades\File;

function deprecatedApiDocsPage(string $path): string
{
    return File::get(dirname(__DIR__, 3)."/docs/{$path}");
}

it('teaches the filter bar for a relation list, and the custom filter API as deprecated', function () {
    $page = deprecatedApiDocsPage('querying/filters.md');
    $custom = explode('## What you see', explode('## Custom components', $page, 2)[1] ?? '', 2)[0];

    expect($page)->not->toContain('filter panel of a relation table')
        ->not->toContain('relation table keeps a filter panel')
        ->not->toContain('->placement(')
        ->toContain('`immediate()` and `placement()` are deprecated')
        ->and($custom)->toContain('is deprecated', 'shows such a filter as a text key', '`FilterMenu`');
});

it('marks filter() and FilterMenu deprecated in the extension API', function () {
    $page = deprecatedApiDocsPage('extending/extension-api.md');
    $row = (string) collect(explode("\n", $page))->first(fn (string $line) => str_starts_with($line, '| `filter(key, component)`'));

    expect($row)->toContain('deprecated', 'text key')
        ->and($page)->toContain('`FilterMenu` is deprecated and stays in 1.x');
});

it('describes the create sheet of a relation field with no modalSize() example', function () {
    $page = deprecatedApiDocsPage('your-first-resource/relationships.md');

    expect($page)->toContain('480 px side sheet')
        ->not->toContain('->modalSize(')
        ->not->toContain('in a modal');
});
