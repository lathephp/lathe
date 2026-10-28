<?php

namespace Lathe\Preferences;

/**
 * Whether a user last chose the table or the Board on a Resource's list.
 */
class LayoutChoice
{
    /** @var list<string> */
    public const array LAYOUTS = ['table', 'board'];

    /**
     * The preference key a Resource's layout is saved under.
     */
    public static function key(string $uriKey): string
    {
        return "layout:{$uriKey}";
    }
}
