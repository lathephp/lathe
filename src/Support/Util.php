<?php

namespace Lathe\Support;

use Illuminate\Http\Request;

class Util
{
    /**
     * Whether the request targets the panel: its path is the panel path or below it,
     * on the panel domain when one is set.
     */
    public static function isLatheRequest(Request $request): bool
    {
        $config = app(ConfigStore::class);
        $domain = $config->nullableString('domain');

        if ($domain !== null && $request->getHost() !== $domain) {
            return false;
        }

        $path = trim($config->string('path'), '/');

        return $path === ''
            || $request->is($path)
            || $request->is($path.'/*');
    }
}
