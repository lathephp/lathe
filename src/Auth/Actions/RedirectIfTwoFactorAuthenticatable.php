<?php

namespace Lathe\Auth\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable as FortifyRedirect;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Symfony\Component\HttpFoundation\Response;

/**
 * The login step that sends a user with two-factor to the panel's challenge screen.
 *
 * Fortify's own step redirects to the two-factor.login route, which belongs to the host.
 *
 * @internal
 */
class RedirectIfTwoFactorAuthenticatable extends FortifyRedirect
{
    /**
     * @param  Request  $request
     * @param  Model  $user
     */
    protected function twoFactorChallengeResponse($request, $user): Response
    {
        $request->session()->put([
            'login.id' => $user->getKey(),
            'login.remember' => $request->boolean('remember'),
        ]);

        // @phpstan-ignore argument.type (Fortify types the user as App\Models\User, a class the panel cannot assume)
        TwoFactorAuthenticationChallenged::dispatch($user);

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => true])
            : redirect()->route('lathe.two-factor.login');
    }
}
