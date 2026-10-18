<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Http\JsonResponse;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\PivotVia;
use Lathe\Query\Selection;

/**
 * Runs one action of the Resource on the selection in the body: 404 for an unknown action,
 * 403 for a hidden one. Answers {result, permitted, skipped, failed, errors}.
 */
class ActionRunController
{
    use AuthorizesResources;

    public function __invoke(LatheRequest $request, ResourceAuthorization $authorization): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);
        $key = $request->input('action');
        $key = is_string($key) ? $key : '';
        $action = $class::make()->availableActions($request)->findByUriKey($key);

        // A pivot action belongs to the parent's relation field the selection's via names.
        if ($action === null && $request->has('selection') && $request->selection()->via !== null) {
            $action = PivotVia::resolve($request, $class, (string) $request->selection()->via)->actions($request)->findByUriKey($key);
        }

        abort_if($action === null, 404);
        abort_unless($action->authorizedToSee($request), 403);

        // Here, before any run, queue or subclass's own handleRequest, as RequirePassword answers JSON.
        if ($action->needsPasswordConfirmation()) {
            return new JsonResponse(['message' => 'Password confirmation required.'], 423);
        }

        // A standalone action runs on no rows, so it reads no selection.
        $selection = $action->isStandalone() ? new Selection('ids') : $request->selection();

        return new JsonResponse($action->handleRequest($request, $selection));
    }
}
