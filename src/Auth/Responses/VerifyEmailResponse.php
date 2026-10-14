<?php

namespace Lathe\Auth\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Lathe\Lathe;
use Symfony\Component\HttpFoundation\Response;

/**
 * After the signed link: the page the user wanted, else the panel's initial path.
 *
 * @internal
 */
class VerifyEmailResponse implements VerifyEmailResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect()->intended(Lathe::resolveInitialPath($request));
    }
}
