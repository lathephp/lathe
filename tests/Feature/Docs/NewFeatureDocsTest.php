<?php

use Illuminate\Support\Facades\File;

function newFeaturePage(string $path): string
{
    return File::get(dirname(__DIR__, 3)."/docs/{$path}");
}

/**
 * @return list<string> the php code blocks of a page
 */
function newFeaturePhpBlocks(string $page): array
{
    preg_match_all('/```php\n(.*?)```/s', $page, $matches);

    return $matches[1];
}

it('names each new theme token on the layout page', function (string $token) {
    expect(newFeaturePage('organizing/layout.md'))->toContain("`{$token}`");
})->with([
    'brand', 'focus',
    'primary-subtle', 'primary-subtle-foreground',
    'success-subtle', 'success-subtle-foreground',
    'warning-subtle', 'warning-subtle-foreground',
    'info-subtle', 'info-subtle-foreground',
    'destructive-subtle', 'destructive-subtle-foreground',
    'skeleton', 'scrim', 'chart-other', 'sidebar-hover',
])->group('frontend');

it('sets a design system token in a theme.colors example', function () {
    expect(implode("\n", newFeaturePhpBlocks(newFeaturePage('organizing/layout.md'))))->toContain("'success-subtle' =>");
})->group('frontend');

it('documents the column picker and its routes on the index page', function () {
    expect(newFeaturePage('your-first-resource/defining-resources.md'))
        ->toContain('api/resources/{resource}/columns', 'lathe_user_preferences')
        ->toMatch('/```json\n\{"order": \[[^\n]*"hidden": \[[^\n]*"pinned": \[/');
})->group('frontend');

it('shows phoneCard() on the resource page', function () {
    expect(implode("\n", newFeaturePhpBlocks(newFeaturePage('your-first-resource/defining-resources.md'))))->toContain('phoneCard(');
})->group('frontend');

it('shows link() and names the CSV menu item on the widgets page', function () {
    $page = newFeaturePage('organizing/widgets.md');

    expect(implode("\n", newFeaturePhpBlocks($page)))->toContain('link(')
        ->and($page)->toContain('Download CSV');
})->group('frontend');

it('shows customizable(false) in a php block on the dashboards page', function () {
    expect(implode("\n", newFeaturePhpBlocks(newFeaturePage('organizing/dashboards.md'))))->toContain('customizable(false)');
})->group('frontend');

it('names each workspace key on the workspace page', function (string $key) {
    expect(newFeaturePage('querying/workspace.md'))->toContain($key);
})->with([
    'Home', 'End', 'Page Up', 'Page Down', 'Enter', 'F2', 'Esc', 'Tab', 'Space', 'X', 'Shift', 'Ctrl', 'Cmd',
    '`?`', 'Ctrl/Cmd+A', 'Ctrl/Cmd+Z',
])->group('frontend');

it('shows the filter bar grammar and the multiple() method on the filter bar page', function (string $token) {
    expect(newFeaturePage('querying/filter-bar.md'))->toContain($token);
})->with([
    'status:pending', 'status:pending,paid', 'total:>1500', 'total:10..50', 'customer:"Ann Taylor"', 'multiple()',
])->group('frontend');

it('shows board() and the board index on the board page', function () {
    $page = newFeaturePage('querying/board.md');

    expect(implode("\n", newFeaturePhpBlocks($page)))->toContain("->board('status')")
        ->and($page)->toMatch('/index[^\n]*board field[^\n]*sort column/i');
})->group('frontend');

it('shows a status meter with an exception on the status meters page', function () {
    expect(implode("\n", newFeaturePhpBlocks(newFeaturePage('your-first-resource/status-meters.md'))))->toContain("->meter(except: ['cancelled'])");
})->group('frontend');

it('no longer says a click on a row opens the detail page', function () {
    expect(newFeaturePage('your-first-resource/defining-resources.md'))->not->toContain('A click on a row opens the detail page');
})->group('frontend');
