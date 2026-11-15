<?php

use Symfony\Component\Finder\Finder;

// The hooks shadcn 4.21.0's own style files leave undefined: a part carries the class so a
// style could draw it, and none does. cn-rtl-flip is drawn by the panel's app.css instead.
const STYLE_COVERAGE_UNDEFINED_EVERYWHERE = [
    'cn-accordion-trigger-icon', 'cn-alert-dialog-action', 'cn-alert-dialog-cancel', 'cn-avatar-group',
    'cn-breadcrumb', 'cn-card-action', 'cn-chart', 'cn-combobox-chip-indicator-icon',
    'cn-combobox-chip-input', 'cn-combobox-clear', 'cn-combobox-clear-icon', 'cn-combobox-group',
    'cn-combobox-input', 'cn-combobox-item-indicator-icon', 'cn-command-item-indicator',
    'cn-field-orientation-horizontal', 'cn-field-orientation-responsive', 'cn-field-orientation-vertical',
    'cn-font-heading', 'cn-menu-target', 'cn-pagination', 'cn-pagination-link', 'cn-pagination-next-text',
    'cn-pagination-previous-text', 'cn-progress-root', 'cn-rtl-flip', 'cn-scroll-area',
    'cn-select-item-indicator-icon', 'cn-sidebar-trigger', 'cn-tabs-list-variant-default',
    'cn-tabs-list-variant-line',
];
const STYLE_COVERAGE_UNDEFINED_IN = [
    'nova' => ['cn-accordion', 'cn-calendar-caption', 'cn-input-group-button-size-sm', 'cn-menu-translucent'],
    'vega' => ['cn-accordion', 'cn-alert-dialog-footer', 'cn-calendar-caption', 'cn-input-group-button-size-sm'],
    'lyra' => ['cn-accordion', 'cn-alert-dialog-footer', 'cn-button-group-orientation-horizontal', 'cn-button-group-orientation-vertical', 'cn-calendar-caption', 'cn-dialog-footer'],
    'maia' => ['cn-alert-dialog-footer', 'cn-calendar-caption', 'cn-input-group-button-size-sm'],
    'mira' => ['cn-alert-dialog-footer', 'cn-calendar-caption'],
    'luma' => ['cn-alert-dialog-footer', 'cn-calendar-caption', 'cn-input-group-button-size-sm'],
    'rhea' => ['cn-alert-dialog-footer', 'cn-calendar-caption', 'cn-input-group-button-size-sm'],
    'sera' => ['cn-accordion', 'cn-alert-dialog-footer', 'cn-input-group-button-size-sm'],
];

/** @return list<string> every cn-* class the kit and the panel write, outside their tests */
function styleCoverageUsed(): array
{
    $root = dirname(__DIR__, 3);
    $used = [];

    foreach (Finder::create()->files()->in(["{$root}/packages/ui/src", "{$root}/resources/js"])->name(['*.ts', '*.tsx'])->notPath('#(^|/)__tests__/#')->notName('*.test.*') as $file) {
        preg_match_all('/(?<![\w-])cn-[a-z0-9-]+/', $file->getContents(), $matches);
        $used = [...$used, ...$matches[0]];
    }

    // A name that ends in a dash is the head of a template, such as cn-button-variant-${variant}.
    $used = array_filter(array_unique($used), fn (string $class) => ! str_ends_with($class, '-'));
    sort($used);

    return array_values($used);
}

it('defines in the style every class the kit and the panel use', function (string $style) {
    $css = (string) file_get_contents(dirname(__DIR__, 3)."/resources/css/styles/style-{$style}.css");
    preg_match_all('/\.(cn-[a-z0-9-]+)/', $css, $defined);
    $allowed = [...STYLE_COVERAGE_UNDEFINED_EVERYWHERE, ...STYLE_COVERAGE_UNDEFINED_IN[$style]];

    $missing = array_values(array_diff(styleCoverageUsed(), $defined[1], $allowed));
    $stale = array_values(array_intersect($allowed, $defined[1]));

    expect($missing)->toBe([])
        ->and($stale)->toBe([]);
})->with(['nova', 'vega', 'lyra', 'maia', 'mira', 'luma', 'rhea', 'sera']);
