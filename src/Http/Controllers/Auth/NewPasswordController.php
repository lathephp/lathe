<?php

namespace Lathe\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Http\Controllers\NewPasswordController as FortifyNewPasswordController;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Reset password screen a mailed link opens, and the request that sets the password.
 */
class NewPasswordController
{
    use CallsFortify;

    public function create(Request $request, string $token): Response
    {
        $email = $request->query('email');

        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => is_string($email) ? $email : '',
        ])->toResponse($request);
    }

    public function store(Request $request): Response
    {
        return $this->callFortify($request, FortifyNewPasswordController::class, 'store');
    }
}
