<?php

namespace Lathe\Http\Controllers\Auth;

use Inertia\Inertia;
use Inertia\Response;
use Lathe\Auth\FortifyConfig;

class LoginPageController
{
    public function __invoke(FortifyConfig $fortify): Response
    {
        return Inertia::render('Auth/Login', [
            'username' => $fortify->username(),
        ]);
    }
}
