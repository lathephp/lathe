<?php

namespace Lathe\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController as FortifyPasswordResetLinkController;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Forgot password screen, and the request that mails a reset link.
 */
class PasswordResetLinkController
{
    use CallsFortify;

    public function create(Request $request): Response
    {
        $status = $request->session()->get('status');

        return Inertia::render('Auth/ForgotPassword', [
            'status' => is_string($status) ? $status : null,
        ])->toResponse($request);
    }

    public function store(Request $request): Response
    {
        return $this->callFortify($request, FortifyPasswordResetLinkController::class, 'store');
    }
}
