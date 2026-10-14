<?php

namespace Lathe\Http\Controllers\Auth;

use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;
use Lathe\Lathe;
use Symfony\Component\HttpFoundation\Response;

/**
 * The two-factor challenge a user with two-factor meets after a correct password.
 */
class TwoFactorChallengeController
{
    use CallsFortify;

    public function create(Request $request): Response
    {
        // A correct password leaves login.id in the session; without it there is nobody to challenge.
        if (! $request->session()->has('login.id')) {
            return redirect(Lathe::authPaths()['login'] ?? '/');
        }

        return Inertia::render('Auth/TwoFactorChallenge')->toResponse($request);
    }

    public function store(Request $request): Response
    {
        // Fortify's two-factor limiter only guards Fortify's own route, and a named limiter would
        // be lost with cached routes, so the panel counts here: 5 tries a minute per login session.
        $id = $request->session()->get('login.id');
        $key = 'lathe.two-factor|'.(is_scalar($id) ? $id : $request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw new ThrottleRequestsException(headers: ['Retry-After' => RateLimiter::availableIn($key)]);
        }

        RateLimiter::hit($key, 60);

        return $this->callFortify($request, TwoFactorAuthenticatedSessionController::class, 'store');
    }
}
