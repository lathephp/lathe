<?php

use Symfony\Component\Finder\Finder;

/** @return string the utility with its variants taken off, the variants being everything before the last colon outside brackets */
function styleSidesBase(string $utility): string
{
    $depth = 0;
    $cut = -1;

    foreach (str_split($utility) as $index => $char) {
        $depth += match ($char) {
            '[', '(' => 1,
            ']', ')' => -1,
            default => 0,
        };

        if ($char === ':' && $depth === 0) {
            $cut = $index;
        }
    }

    return substr($utility, $cut + 1);
}

// A style that names a physical side draws the close button, a check mark or an inset
// on the wrong side under right to left; the logical start and end follow the direction.
it('names only logical sides in the style files', function () {
    $root = dirname(__DIR__, 3);
    $physical = '/^-?([mp][lr]-|left-|right-|text-(left|right)$|rounded-(l|r|tl|tr|bl|br)(-|$)|border-[lr](-|$))/';
    $found = [];

    foreach (Finder::create()->files()->in("{$root}/resources/css/styles")->name('style-*.css') as $file) {
        preg_match_all('/@apply ([^;}]+)/', $file->getContents(), $rules);

        foreach (preg_split('/\s+/', implode(' ', $rules[1])) ?: [] as $utility) {
            $base = rtrim(styleSidesBase($utility), '!');

            // A sheet's or drawer's own side, and a centred inset, are physical on purpose:
            // their slide-in animations move along the physical x axis.
            $keyedToSide = preg_match('/data-\[(side|vaul-drawer-direction)=(left|right)\]|data-side-(left|right)/', $utility) === 1;

            if (preg_match($physical, $base) === 1 && ! $keyedToSide && $base !== 'left-1/2') {
                $found[] = $file->getFilename().': '.$utility;
            }
        }
    }

    expect(array_values(array_unique($found)))->toBe([]);
});

it('takes the variants off a utility only at a colon outside brackets', function () {
    expect(styleSidesBase('**:data-[slot=accordion-trigger-icon]:ml-auto'))->toBe('ml-auto')
        ->and(styleSidesBase('[&:not(:first-child)]:border-l-0'))->toBe('border-l-0')
        ->and(styleSidesBase('pr-2'))->toBe('pr-2');
});
