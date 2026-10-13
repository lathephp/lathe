<?php

namespace Lathe\Filters;

use Lathe\Http\Requests\LatheRequest;

/**
 * A checkbox per option. The value maps option => bool; a list turns on the options it names.
 */
abstract class BooleanFilter extends Filter
{
    public function component(): string
    {
        return 'boolean-filter';
    }

    /**
     * @return array<string, bool>
     */
    public function normalize(LatheRequest $request, mixed $value, bool $remote): array
    {
        if (! is_array($value)) {
            $this->invalid();
        }

        $options = array_map(fn (mixed $option) => is_scalar($option) ? (string) $option : '', array_values($this->options($request)));
        $flags = array_is_list($value) ? array_fill_keys(array_map(fn (mixed $item) => is_string($item) ? $item : '', $value), '1') : $value;
        $result = [];

        foreach ($flags as $option => $flag) {
            if (! in_array((string) $option, $options, true) || ! in_array($flag, ['1', '0', 'true', 'false'], true)) {
                $this->invalid();
            }

            $result[(string) $option] = $flag === '1' || $flag === 'true';
        }

        return $result;
    }
}
