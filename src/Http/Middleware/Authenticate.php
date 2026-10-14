<?php

namespace Lathe\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Lathe\Auth\FortifyConfig;
use Lathe\Lathe;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets only a user signed in on the panel guard through: 401 JSON for the API, the login page otherwise.
 */
class Authenticate
{
    public function __construct(protected AuthFactory $auth, protected FortifyConfig $fortify)
    {
        //
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->auth->guard($this->fortify->guard())->check()) {
            return $next($request);
        }

        if (static::answersJson($request)) {
            return response()->json(['message' => Lathe::__('Unauthenticated.')], 401);
        }

        return redirect()->guest(Lathe::authPaths()['login'] ?? '/login');
    }

    /**
     * Whether a refusal answers JSON. The API group runs this middleware before EnsureJson,
     * so a URL under {path}/api answers JSON whatever its Accept header says.
     */
    public static function answersJson(Request $request): bool
    {
        $api = ltrim(trim(Lathe::path(), '/').'/api', '/');

        return $request->expectsJson() || $request->is($api, $api.'/*');
    }
}
