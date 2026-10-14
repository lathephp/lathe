<?php

namespace Lathe\Auth\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\PasswordUpdateResponse as PasswordUpdateResponseContract;
use Lathe\Lathe;
use Symfony\Component\HttpFoundation\Response;

/**
 * After a password change: back to the Security page with a toast.
 *
 * @internal
 */
class PasswordUpdateResponse implements PasswordUpdateResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        return $request->wantsJson()
            ? new JsonResponse(['message' => Lathe::__('Password updated.')])
            : back()->with('success', Lathe::__('Password updated.'));
    }
}
