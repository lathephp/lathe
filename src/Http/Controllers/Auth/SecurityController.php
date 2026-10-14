<?php

namespace Lathe\Http\Controllers\Auth;

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
        ])->toResponse($request);
    }

    public function updatePassword(Request $request): Response
    {
        return $this->callFortify($request, PasswordController::class, 'update');
    }
}
