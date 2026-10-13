<?php

namespace Workbench\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves one request that sends `X-Bench-Locale: ar` in Arabic and right-to-left, so the
 * Octane lane can check that the locale and direction do not reach the next request.
 */
class ArabicContext
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->header('X-Bench-Locale') !== 'ar') {
            return $next($request);
        }

        $before = app()->getLocale();
        app()->setLocale('ar');
        $request->attributes->set('lathe.rtl', true);

        try {
            return $next($request);
        } finally {
            app()->setLocale($before);
        }
    }
}
