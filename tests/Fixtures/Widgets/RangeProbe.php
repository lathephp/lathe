<?php

namespace Lathe\Tests\Fixtures\Widgets;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\Widget;

/**
 * A card whose value is the range it was asked for, offering the ranges the test sets.
 */
class RangeProbe extends Widget
{
    /** @var array<int|string, string> */
    public static array $ranges = [];

    protected ?string $component = 'value-widget';

    public function ranges(): array
    {
        return static::$ranges;
    }

    public function calculate(LatheRequest $request): mixed
    {
        return $request->query('range');
    }
}
