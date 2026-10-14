<?php

namespace Lathe\Http\Controllers\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Http\Controllers\PasswordController;
use Lathe\Lathe;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Security page, where a user changes their password and manages two-factor.
 */
class SecurityController
{
    use CallsFortify;

    public function show(Request $request): Response
    {
        $fortify = Lathe::fortify();

        abort_unless($fortify->hasSecurityFeatures(), 404);

        return Inertia::render('Security', [
            'features' => [
                'updatePasswords' => $fortify->enabled('updatePasswords'),
                'twoFactorAuthentication' => $fortify->enabled('twoFactorAuthentication'),
            ],
            'twoFactor' => $this->twoFactorState($request),
        ])->toResponse($request);
    }

    public function updatePassword(Request $request): Response
    {
        return $this->callFortify($request, PasswordController::class, 'update');
    }

    /**
     * Off with no secret; Pending while a code must still confirm it; On after.
     */
    protected function twoFactorState(Request $request): string
    {
        $user = $request->user(Lathe::fortify()->guard());

        if (! $user instanceof Model || $user->getAttribute('two_factor_secret') === null) {
            return 'off';
        }

        return Lathe::fortify()->twoFactorOption('confirm') && $user->getAttribute('two_factor_confirmed_at') === null ? 'pending' : 'on';
    }
}
