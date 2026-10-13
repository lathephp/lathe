<?php

namespace Lathe\Events;

use Illuminate\Http\Request;

/**
 * Fired once at the start of every panel request, never for a host route.
 */
class ServingLathe
{
    public function __construct(public Request $request)
    {
        //
    }
}
