<?php

namespace Lathe\Auth\Responses;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedTwoFactorLoginResponse as FailedTwoFactorLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * A wrong code or recovery code: 422 for JSON, else back to the panel's challenge with the error.
 *
 * @internal
 */
class FailedTwoFactorLoginResponse implements FailedTwoFactorLoginResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        [$key, $message] = $request->filled('recovery_code')
            ? ['recovery_code', __('The provided two factor recovery code was invalid.')]
            : ['code', __('The provided two factor authentication code was invalid.')];

        if ($request->wantsJson()) {
            throw ValidationException::withMessages([$key => [$message]]);
        }

        return redirect()->route('lathe.two-factor.login')->withErrors([$key => $message]);
    }
}
