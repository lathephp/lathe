<?php

namespace Workbench\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends the strict policy the panel must run under: scripts and styles from this
 * origin or with the request's nonce, no inline code and no eval.
 */
class StrictContentSecurityPolicy
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('workbench.strict_csp')) {
            return $next($request);
        }

        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'nonce-{$nonce}'",
        );

        return $response;
    }
}
