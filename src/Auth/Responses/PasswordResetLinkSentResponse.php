<?php

namespace Lathe\Auth\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Lathe\Lathe;
use Symfony\Component\HttpFoundation\Response;

/**
 * The one answer to a reset-link request, sent or not.
 *
 * An unknown address and a request inside the broker's throttle get the same answer as a
 * sent link, so the screen never shows which addresses have an account.
 *
 * @internal
 */
class PasswordResetLinkSentResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $message = Lathe::__('We have emailed your password reset link.');

        return $request->wantsJson()
            ? new JsonResponse(['message' => $message])
            : back()->with('status', $message);
    }
}
