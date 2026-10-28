<?php

use Lathe\Theme\Color;

/** The tokens app.css defines in one of its three default blocks, as name => value. */
function defaultTokens(string $block): array
{
    $css = (string) file_get_contents(dirname(__DIR__, 3).'/resources/css/app.css');
    $start = strpos($css, "{$block} {");
    expect($start)->not->toBeFalse("app.css has no {$block} block");
    $body = substr($css, (int) strpos($css, '{', (int) $start) + 1);
    $body = substr($body, 0, (int) strpos($body, '}'));
    preg_match_all('/--([\w-]+):\s*([^;]+);/', $body, $matches);

    return array_combine($matches[1], array_map('trim', $matches[2]));
}

/** The light block, the dark media query and the .dark class. */
dataset('default blocks', [
    'light' => [':where(:root)'],
    'system dark' => [':where(:root:not(.light))'],
    'dark class' => ["\n.dark"],
]);

const NEW_TOKENS = [
    'brand', 'focus', 'skeleton', 'scrim', 'chart-other', 'sidebar-hover',
    'primary-subtle', 'primary-subtle-foreground', 'success-subtle', 'success-subtle-foreground',
    'warning-subtle', 'warning-subtle-foreground', 'info-subtle', 'info-subtle-foreground',
    'destructive-subtle', 'destructive-subtle-foreground',
];

it('defaults to the layout blue primary on steel in light and dark', function () {
    expect(defaultTokens(':where(:root)'))->toMatchArray(['primary' => '#2e47e0', 'background' => '#eef1f2', 'card' => '#ffffff'])
        ->and(defaultTokens(':where(:root:not(.light))'))->toMatchArray(['primary' => '#8796ff', 'background' => '#0f1315'])
        ->and(defaultTokens("\n.dark"))->toMatchArray(['primary' => '#8796ff', 'background' => '#0f1315']);
});

it('defaults to the 0.5rem radius, 6 px controls and 8 px panels', function () {
    expect(defaultTokens(':where(:root)')['radius'])->toBe('0.5rem')
        ->and(config('lathe.theme.radius'))->toBe('0.5rem');
});

it('defines every one of the 56 token names', function (string $block) {
    expect(array_keys(defaultTokens($block)))->toContain(...array_map(fn (Color $color) => $color->value, Color::cases()));
})->with('default blocks');

it('defines the design system tokens and shadows', function (string $block) {
    expect(array_keys(defaultTokens($block)))->toContain(...NEW_TOKENS, ...['shadow-xs', 'shadow-sm', 'shadow-md', 'shadow-lg']);
})->with('default blocks');

it('gives the dark media query and the dark class the same values', function () {
    expect(defaultTokens(':where(:root:not(.light))'))->toBe(defaultTokens("\n.dark"));
});

it('maps each new token to a utility colour for the panel and for plugins', function (string $file) {
    $css = (string) file_get_contents(dirname(__DIR__, 3)."/{$file}");

    foreach (NEW_TOKENS as $name) {
        expect($css)->toContain("--color-{$name}: var(--{$name});");
    }
})->with(['resources/css/app.css', 'packages/core/tokens.css']);

it('defines the radius steps as variables, since the styles read var(--radius-md) and its kin', function () {
    // @theme inline writes the steps into utilities only, so the page needs them as variables too.
    expect(defaultTokens(':where(:root)'))->toMatchArray([
        'radius-sm' => 'calc(var(--radius) - 4px)',
        'radius-md' => 'calc(var(--radius) - 2px)',
        'radius-lg' => 'var(--radius)',
        'radius-xl' => 'calc(var(--radius) + 4px)',
    ]);
});
