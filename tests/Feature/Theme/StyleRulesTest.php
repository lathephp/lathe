<?php

const STYLE_RULES_STYLES = ['nova', 'vega', 'lyra', 'maia', 'mira', 'luma', 'rhea', 'sera'];

/** @return list<string> the utilities a style's rule for one cn-* class applies */
function styleRule(string $style, string $class): array
{
    $css = (string) file_get_contents(dirname(__DIR__, 3)."/resources/css/styles/style-{$style}.css");

    if (preg_match('/^\s*\.'.preg_quote($class, '/').' \{\s*@apply ([^;}]+)/m', $css, $match) !== 1) {
        return [];
    }

    return preg_split('/\s+/', trim($match[1])) ?: [];
}

it('fills an indeterminate checkbox as it fills a checked one', function (string $style) {
    $rule = styleRule($style, 'cn-checkbox');
    $fills = array_filter($rule, fn (string $utility) => preg_match('/^(dark:)?data-checked:(bg|text|border)-/', $utility) === 1);

    expect($fills)->not->toBeEmpty();
    foreach ($fills as $fill) {
        expect($rule)->toContain(str_replace('data-checked:', 'data-indeterminate:', $fill));
    }
})->with(STYLE_RULES_STYLES);

it('dims a disabled nova checkbox and radio to its own 70 percent over the kit default', function (string $class) {
    expect(styleRule('nova', $class))->toContain('data-disabled:opacity-70!');
})->with(['cn-checkbox', 'cn-radio-group-item']);

it('holds the focus ring of each kit part in its style rule', function (string $style, string $class) {
    $rings = array_filter(styleRule($style, $class), fn (string $utility) => preg_match('/^(?:dark:)?(?:focus-visible|group-data-\[focused=true\]\/day):(?:[\w-]+:)*(?:ring-|border-(?:[a-z]-)?ring)/', $utility) === 1);

    expect($rings)->not->toBeEmpty();
})->with(STYLE_RULES_STYLES)->with(['cn-toggle', 'cn-badge', 'cn-item', 'cn-tabs-trigger', 'cn-scroll-area-viewport', 'cn-calendar-day-button', 'cn-input-otp-slot']);

// Base UI never sets data-active on an OTP slot, so a ring keyed to it never showed.
// Sera draws its slot as an underline, whose focus look is the bottom border.
it('keys the OTP slot ring to the focus and to an invalid value', function (string $style) {
    $rule = implode(' ', styleRule($style, 'cn-input-otp-slot'));

    expect($rule)->not->toContain('data-[active=true]')
        ->toMatch('/(^| )focus-visible:(ring-|border-(b-)?ring)/')
        ->toMatch('/(^| )(focus-visible:)?aria-invalid:/');
})->with(STYLE_RULES_STYLES);

// Nova rings a control you press with a gap in its focus colour.
it('rings a nova control you press with the offset focus ring', function (string $class) {
    $rule = styleRule('nova', $class);

    expect($rule)->toContain('focus-visible:ring-2', 'focus-visible:ring-focus', 'focus-visible:ring-offset-2', 'focus-visible:ring-offset-background')
        ->and(array_values(preg_grep('/^focus-visible:(ring-(\d|\[)|ring-ring|border-ring)/', $rule) ?: []))->toBe(['focus-visible:ring-2']);
})->with(['cn-button', 'cn-toggle', 'cn-toggle-group-item', 'cn-checkbox', 'cn-radio-group-item', 'cn-switch', 'cn-slider-thumb', 'cn-calendar-day-button', 'cn-badge', 'cn-tabs-trigger', 'cn-accordion-trigger']);

// Nova rings a field you type in with its border and a 1 px ring in the ring colour.
it('rings a nova field you type in with its border', function (string $class) {
    $rule = implode(' ', styleRule('nova', $class));

    expect($rule)->toMatch('/(^| )(focus-visible|has-\[\[data-slot=input-group-control\]:focus-visible\]):border-ring( |$)/')
        ->toMatch('/(^| )(focus-visible|has-\[\[data-slot=input-group-control\]:focus-visible\]):ring-1( |$)/')
        ->toMatch('/(^| )(focus-visible|has-\[\[data-slot=input-group-control\]:focus-visible\]):ring-ring( |$)/')
        ->not->toMatch('/focus-visible\]?:ring-(3|2|\[)/');
})->with(['cn-input', 'cn-textarea', 'cn-select-trigger', 'cn-input-otp-slot', 'cn-input-group']);

// The CodeEditor draws its box with cn-textarea, but the focus sits in CodeMirror's content
// inside it, so the box shows the textarea's ring through focus-within.
it('rings a box holding the focus as it rings a focused textarea', function (string $style) {
    $rule = styleRule($style, 'cn-textarea');
    $rings = preg_grep('/^focus-visible:/', $rule) ?: [];

    expect($rings)->not->toBeEmpty();
    foreach ($rings as $ring) {
        expect($rule)->toContain(str_replace('focus-visible:', 'focus-within:', $ring));
    }
})->with(STYLE_RULES_STYLES);

// The sidebar tokens are full colours, so hsl() around one made the outline's shadow invalid.
it('outlines a sidebar button with the sidebar border colour itself', function (string $style) {
    $rule = implode(' ', styleRule($style, 'cn-sidebar-menu-button-variant-outline'));

    expect($rule)->toContain('var(--sidebar-border)')->not->toContain('hsl(');
})->with(STYLE_RULES_STYLES);

it('animates a skeleton from its style, and holds it still for reduced motion', function (string $style) {
    $rule = styleRule($style, 'cn-skeleton');

    expect(preg_grep('/^animate-(?!none)/', $rule))->not->toBeEmpty()
        ->and($rule)->toContain('motion-reduce:animate-none');
})->with(STYLE_RULES_STYLES);
