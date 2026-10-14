<?php

namespace Lathe\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lathe\Auth\FortifyConfig;

class LoginPageController
{
    public function __invoke(Request $request, FortifyConfig $fortify): Response
    {
        $status = $request->session()->get('status');

        return Inertia::render('Auth/Login', [
            'username' => $fortify->username(),
            // "Your password has been reset." after the reset screen.
            'status' => is_string($status) ? $status : null,
        ]);
    }
}
