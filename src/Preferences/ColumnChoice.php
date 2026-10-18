<?php

namespace Lathe\Preferences;

/**
 * A user's columns of a Resource index, aged against the columns the code has now: a column
 * removed from code drops out, and one added in code shows at the end.
 */
class ColumnChoice
{
    /**
     * The preference key a Resource's column choice is saved under.
     */
    public static function key(string $uriKey): string
    {
        return "columns:{$uriKey}";
    }

    /**
     * The choice with only the code's keys, each once. Code keys the order lacks join its end.
     *
     * @param  list<string>  $codeKeys
     * @param  array<array-key, mixed>  $choice
     * @return array{order: list<string>, hidden: list<string>, pinned: list<string>}
     */
    public static function age(array $codeKeys, array $choice): array
    {
        $order = self::known($codeKeys, $choice['order'] ?? []);
        $hidden = self::known($codeKeys, $choice['hidden'] ?? []);

        return [
            // Every code column has a place in the order, the hidden ones too, so the picker lists them all.
            'order' => [...$order, ...array_values(array_diff($codeKeys, $order))],
            'hidden' => $hidden,
            'pinned' => self::known($codeKeys, $choice['pinned'] ?? []),
        ];
    }

    /**
     * The keys of the columns to show, in order. With no choice that is the code's order.
     *
     * @param  list<string>  $codeKeys
     * @param  array<array-key, mixed>|null  $choice
     * @return list<string>
     */
    public static function visible(array $codeKeys, ?array $choice): array
    {
        if ($choice === null) {
            return $codeKeys;
        }

        $aged = self::age($codeKeys, $choice);

        return array_values(array_diff($aged['order'], $aged['hidden']));
    }

    /**
     * @param  list<string>  $codeKeys
     * @return list<string>
     */
    protected static function known(array $codeKeys, mixed $keys): array
    {
        if (! is_array($keys)) {
            return [];
        }

        return array_values(array_unique(array_filter($keys, fn (mixed $key) => is_string($key) && in_array($key, $codeKeys, true))));
    }
}
