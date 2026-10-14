<?php

namespace Lathe\Widgets;

/**
 * The 15-colour palette charts hand out in key order. Slots 1 to 5 are theme tokens, so
 * they follow light and dark mode; slots 6 to 15 are fixed hues that read on both card colours.
 */
class PartitionColors
{
    private const array PALETTE = [
        'var(--chart-1)',
        'var(--chart-2)',
        'var(--chart-3)',
        'var(--chart-4)',
        'var(--chart-5)',
        '#ef4444',
        '#ea580c',
        '#a16207',
        '#16a34a',
        '#0d9488',
        '#0e7490',
        '#a855f7',
        '#d946ef',
        '#0284c7',
        '#059669',
    ];

    /**
     * A colour per key: the user's colour where there is one, else the next palette slot.
     * A user colour does not use up a slot, and the 16th key starts the palette over.
     *
     * @param  array<int|string, int|string>  $keys
     * @param  array<int|string, string>  $userColors  keyed by label
     * @return array<int|string, string>
     */
    public static function forKeys(array $keys, array $userColors = []): array
    {
        $colors = [];
        $slot = 0;

        foreach ($keys as $key) {
            $colors[$key] = $userColors[$key] ?? self::PALETTE[$slot++ % count(self::PALETTE)];
        }

        return $colors;
    }
}
