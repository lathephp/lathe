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
