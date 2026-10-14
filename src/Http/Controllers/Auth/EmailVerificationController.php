<?php

namespace Lathe\Http\Controllers\Auth;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController;
use Laravel\Fortify\Http\Controllers\VerifyEmailController;
use Lathe\Auth\FortifyConfig;
use Lathe\Lathe;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Verify email screen an unverified user is held on, the resend, and the signed link.
 */
class EmailVerificationController
{
    use CallsFortify;

    public function notice(Request $request, FortifyConfig $fortify): Response
    {
        $user = $request->user($fortify->guard());

        if (! $user instanceof MustVerifyEmail || $user->hasVerifiedEmail()) {
            return redirect()->intended(Lathe::resolveInitialPath($request));
        }

        return Inertia::render('Auth/VerifyEmail', [
            'sent' => $request->session()->get('status') === Fortify::VERIFICATION_LINK_SENT,
        ])->toResponse($request);
    }

    public function verify(Request $request): Response
    {
        return $this->callFortify($request, VerifyEmailController::class, '__invoke');
    }

    public function send(Request $request): Response
    {
        return $this->callFortify($request, EmailVerificationNotificationController::class, 'store');
    }
}
