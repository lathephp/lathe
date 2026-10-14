<?php

namespace Lathe\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Http\Controllers\ConfirmablePasswordController as FortifyConfirmablePasswordController;
use Laravel\Fortify\Http\Controllers\ConfirmedPasswordStatusController;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Confirm password screen, the request that confirms, and whether a confirmation is still valid.
 */
class ConfirmablePasswordController
{
    use CallsFortify;

    public function show(Request $request): Response
    {
        return Inertia::render('Auth/ConfirmPassword')->toResponse($request);
    }

    public function store(Request $request): Response
    {
        return $this->callFortify($request, FortifyConfirmablePasswordController::class, 'store');
    }

    public function status(Request $request): Response
    {
        return $this->callFortify($request, ConfirmedPasswordStatusController::class, 'show');
    }
}
