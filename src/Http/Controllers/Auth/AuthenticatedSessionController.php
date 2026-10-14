<?php

namespace Lathe\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController as FortifySessionController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs in and out through Fortify on the panel guard.
 */
class AuthenticatedSessionController
{
    use CallsFortify;

    public function store(Request $request): Response
    {
        return $this->callFortify($request, FortifySessionController::class, 'store');
    }

    public function destroy(Request $request): Response
    {
        return $this->callFortify($request, FortifySessionController::class, 'destroy');
    }
}
