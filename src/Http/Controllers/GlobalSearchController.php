<?php

namespace Lathe\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Search\GlobalSearch;
use Lathe\Support\ConfigStore;

class GlobalSearchController
{
    public function __invoke(LatheRequest $request, ConfigStore $config, Gate $gate, GlobalSearch $search, ResourceAuthorization $authorization): JsonResponse
    {
        abort_unless($config->boolean('global_search.enabled'), 404);

        // An app that never defines globalSearch keeps the palette closed: an undefined ability denies.
        abort_unless($gate->forUser($request->user())->allows('globalSearch'), 403);

        return new JsonResponse($search->search($request, $authorization));
    }
}
