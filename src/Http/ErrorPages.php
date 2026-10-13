<?php

namespace Lathe\Http;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Lathe\Auth\FortifyConfig;
use Lathe\Support\Util;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders 403, 404, 419, 500 and 503 on panel pages as the Error page: inside the shell
 * when a user is signed in, full screen otherwise. JSON requests and host routes keep
 * Laravel's own rendering.
 */
class ErrorPages
{
    public const array STATUSES = [403, 404, 419, 500, 503];

    public function __construct(protected FortifyConfig $fortify)
    {
        //
    }

    public function render(Throwable $e, Request $request): ?Response
    {
        if (! Util::isLatheRequest($request) || $request->expectsJson()) {
            return null;
        }

        $status = match (true) {
            $e instanceof HttpExceptionInterface => $e->getStatusCode(),
            $e instanceof TokenMismatchException => 419,
            $e instanceof AuthorizationException => 403,
            $e instanceof ModelNotFoundException, $e instanceof RecordsNotFoundException => 404,
            // Laravel answers these itself: a redirect back with errors, a login redirect, a ready response.
            $e instanceof ValidationException, $e instanceof AuthenticationException, $e instanceof HttpResponseException => null,
            default => config('app.debug') === true ? null : 500,
        };

        if (! in_array($status, self::STATUSES, true)) {
            return null;
        }

        $message = $status === 403 && $e->getMessage() !== '' && $e->getMessage() !== 'This action is unauthorized.'
            ? $e->getMessage()
            : null;

        Inertia::setRootView('lathe::app');

        return Inertia::render('Error', [
            'status' => $status,
            'message' => $message,
            'shell' => $request->hasSession() && $request->user($this->fortify->guard()) !== null,
        ])->toResponse($request)->setStatusCode($status);
    }
}
