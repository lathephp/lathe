<?php

namespace Lathe\Auth\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Contracts\PasswordResetResponse as PasswordResetResponseContract;
use Lathe\Lathe;
use Symfony\Component\HttpFoundation\Response;

/**
 * After a reset: the panel's login page, saying the password was reset.
 *
 * @internal
 */
class PasswordResetResponse implements PasswordResetResponseContract
{
    public function __construct(protected string $status)
    {
        //
    }

    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $message = $this->status === Password::PASSWORD_RESET ? Lathe::__('Your password has been reset.') : $this->status;

        return $request->wantsJson()
            ? new JsonResponse(['message' => $message])
            : redirect(Lathe::authPaths()['login'] ?? '/')->with('status', $message);
    }
}
