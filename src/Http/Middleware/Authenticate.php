<?php

namespace Lathe\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
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
     * With `unverified`, a user whose email awaits verification passes too (the verify and sign-out routes).
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$options): Response
    {
        $user = $this->auth->guard($this->fortify->guard())->user();

        if ($user !== null) {
            // Fortify's actions read $request->user(), which asks the default guard.
            $this->auth->shouldUse($this->fortify->guard());

            return in_array('unverified', $options, true) || ! $this->awaitsVerification($user) ? $next($request) : $this->holdForVerification($request);
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

    /**
     * Email verification holds a user whose model verifies email and has not yet.
     */
    protected function awaitsVerification(object $user): bool
    {
        return $user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail() && $this->fortify->featureEnabled('emailVerification');
    }

    protected function holdForVerification(Request $request): Response
    {
        return static::answersJson($request)
            ? response()->json(['message' => Lathe::__('Your email address is not verified.')], 403)
            : redirect()->guest(route('lathe.verification.notice'));
    }
}
