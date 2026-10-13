<?php

namespace Lathe\Http\Controllers\Resources;

use Lathe\Auth\ResourceAuthorization;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;

trait AuthorizesResources
{
    /**
     * The Resource the route names: 404 when unknown, 403 when hidden or viewAny is denied.
     *
     * @return class-string<\Lathe\Resources\Resource>
     */
    protected function viewableResource(LatheRequest $request, ResourceAuthorization $authorization): string
    {
        $class = $request->resourceOrFail();

        abort_unless($authorization->canSee($request, $class), 403);
        abort_unless($authorization->allows($request->user(), $class, 'viewAny'), 403, $authorization->denialMessage($class));

        return $class;
    }
}
