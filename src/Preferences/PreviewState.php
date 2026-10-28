<?php

namespace Lathe\Preferences;

/**
 * Whether a user has the preview panel open on a Resource's list.
 */
class PreviewState
{
    /**
     * The preference key a Resource's panel state is saved under.
     */
    public static function key(string $uriKey): string
    {
        return "preview:{$uriKey}";
    }
}
