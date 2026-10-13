<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Http\JsonResponse;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Http\Payloads\IndexPayload;
use Lathe\Http\Requests\LatheRequest;

class IndexController
{
    use AuthorizesResources;

    public function __invoke(LatheRequest $request, ResourceAuthorization $authorization, IndexPayload $payload): JsonResponse
    {
        return new JsonResponse($payload->build($this->viewableResource($request, $authorization)));
    }
}
