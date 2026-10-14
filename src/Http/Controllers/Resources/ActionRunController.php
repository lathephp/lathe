<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Http\JsonResponse;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Http\Requests\LatheRequest;
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
        $action = $class::make()->availableActions($request)->findByUriKey(is_string($key) ? $key : '');

        abort_if($action === null, 404);
        abort_unless($action->authorizedToSee($request), 403);

        // A standalone action runs on no rows, so it reads no selection.
        $selection = $action->isStandalone() ? new Selection('ids') : $request->selection();

        return new JsonResponse($action->handleRequest($request, $selection));
    }
}
