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

/** Whether a utility names a physical side; a sheet's or drawer's own side, and a centred inset, are physical on purpose, since their slides move along the physical x axis. */
function styleSidesPhysical(string $utility): bool
{
    $base = rtrim(styleSidesBase($utility), '!');
    $keyedToSide = preg_match('/data-\[(side|vaul-drawer-direction)=(left|right)\]|data-side-(left|right)/', $utility) === 1;

    return preg_match('/^-?([mp][lr]-|left-|right-|text-(left|right)$|rounded-(l|r|tl|tr|bl|br)(-|$)|border-[lr](-|$))/', $base) === 1
        && ! $keyedToSide && $base !== 'left-1/2';
}

// A style that names a physical side draws the close button, a check mark or an inset
// on the wrong side under right to left; the logical start and end follow the direction.
it('names only logical sides in the style files', function () {
    $root = dirname(__DIR__, 3);
    $found = [];

    foreach (Finder::create()->files()->in("{$root}/resources/css/styles")->name('style-*.css') as $file) {
        preg_match_all('/@apply ([^;}]+)/', $file->getContents(), $rules);

        foreach (preg_split('/\s+/', implode(' ', $rules[1])) ?: [] as $utility) {
            if (styleSidesPhysical($utility)) {
                $found[] = $file->getFilename().': '.$utility;
            }
        }
    }

    expect(array_values(array_unique($found)))->toBe([]);
});

// The same holds for the classes a kit component writes itself, which no style can undo.
it('names only logical sides in the kit components', function () {
    $root = dirname(__DIR__, 3);
    $found = [];

    foreach (Finder::create()->files()->in("{$root}/packages/ui/src")->name('*.tsx')->notPath('#(^|/)__tests__/#') as $file) {
        foreach (preg_split('/[\s\'"`]+/', $file->getContents()) ?: [] as $utility) {
            if (styleSidesPhysical($utility)) {
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
