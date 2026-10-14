<?php

namespace Lathe\Http\Controllers;

use Inertia\Inertia;
use Lathe\Actions\Export\Export;
use Lathe\Actions\Import\Import;
use Lathe\Http\Requests\LatheRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * The page of a queued export or import, for the user who started it. It shows the result
 * whether or not notifications are on; any other user gets 404.
 */
class RunPageController
{
    public function __invoke(LatheRequest $request, string $type, string $id): Response
    {
        $run = ($type === 'export' ? Export::class : Import::class)::find($id);
        $userId = $request->user()?->getAuthIdentifier();

        abort_unless($run !== null && $run->ownedBy(is_int($userId) || is_string($userId) ? $userId : null), 404);

        return Inertia::render('Runs/Show', ['type' => $type, 'id' => $run->getKey()])->toResponse($request);
    }
}
