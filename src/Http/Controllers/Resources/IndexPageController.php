<?php

namespace Lathe\Http\Controllers\Resources;

use Inertia\Inertia;
use Inertia\Response;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Http\Payloads\IndexPayload;
use Lathe\Http\Requests\LatheRequest;

class IndexPageController
{
    use AuthorizesResources;

    public function __invoke(LatheRequest $request, ResourceAuthorization $authorization, IndexPayload $payload): Response
    {
        $class = $this->viewableResource($request, $authorization);

        // Lazy props: the deferred count request reads only the count, not the rows.
        $data = null;
        $part = function (string $key) use (&$data, $payload, $class) {
            $data ??= $payload->build($class);

            return $data[$key];
        };

        return Inertia::render('Resources/Index', [
            'resource' => fn () => $part('resource'),
            'table' => fn () => $part('table'),
            'state' => fn () => $part('state'),
            'count' => Inertia::defer(fn () => $payload->count($class)),
        ]);
    }
}
