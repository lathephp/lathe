<?php

namespace Lathe\Auth\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;

/**
 * Starts and stops impersonation in the session. Lathe::impersonator() resolves it, so an app
 * can bind its own.
 */
interface ImpersonatesUsers
{
    public function impersonate(Request $request, StatefulGuard $guard, Authenticatable $user): bool;

    public function stopImpersonating(Request $request, StatefulGuard $guard, string $userModel): bool;

    public function impersonating(Request $request): bool;

    public function flushImpersonationData(Request $request): void;

    public function redirectAfterStartingImpersonation(Request $request): string;

    public function redirectAfterStoppingImpersonation(Request $request): string;
}
