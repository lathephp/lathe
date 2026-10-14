<?php

use Lathe\Theme\Contrast;

it('measures black on white as 21:1 in every colour notation', function (string $black, string $white) {
    expect(round(Contrast::ratio($black, $white), 1))->toBe(21.0);
})->with([
    'hex' => ['#000000', '#ffffff'],
    'short hex' => ['#000', '#fff'],
    'rgb' => ['rgb(0, 0, 0)', 'rgb(255 255 255)'],
    'hsl' => ['hsl(0 0% 0%)', 'hsl(0, 0%, 100%)'],
    'oklch' => ['oklch(0 0 0)', 'oklch(1 0 0)'],
]);

it('gives the WCAG ratio of mid greys', function () {
    // #777777 on white is 4.48:1; oklch(0.205 0 0) on white is 17.9:1.
    expect(round(Contrast::ratio('#777777', '#ffffff'), 2))->toBe(4.48)
        ->and(round(Contrast::ratio('oklch(0.205 0 0)', 'oklch(1 0 0)'), 1))->toBe(17.9);
});

it('returns null for a colour it cannot read', function () {
    expect(Contrast::ratio('var(--x)', '#fff'))->toBeNull()
        ->and(Contrast::ratio('red', '#fff'))->toBeNull();
});
