<?php

namespace Lathe\Enums;

/**
 * The share of the 12-column widget grid a card spans.
 */
enum CardWidth: string
{
    case Quarter = '1/4';
    case Third = '1/3';
    case Half = '1/2';
    case TwoThirds = '2/3';
    case ThreeQuarters = '3/4';
    case Full = 'full';

    /**
     * The grid columns of 12 this width spans.
     */
    public function columns(): int
    {
        return match ($this) {
            self::Quarter => 3,
            self::Third => 4,
            self::Half => 6,
            self::TwoThirds => 8,
            self::ThreeQuarters => 9,
            self::Full => 12,
        };
    }
}
