<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Http\JsonResponse;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lenses\Lens;

/**
 * A lens's action and batch routes: the lens must be one the user may see (404 unknown,
 * 403 hidden, before anything else), then the selection reads through it and the same
 * handlers as the index's answer.
 */
class LensActionController
{
    use AuthorizesResources;

    public function actions(LatheRequest $request, ResourceAuthorization $authorization): JsonResponse
    {
        $this->viewableLens($request, $authorization);

        return $this->call(ActionController::class, 'index');
    }

    public function form(LatheRequest $request, ResourceAuthorization $authorization, string $resource, string $lens, string $action): JsonResponse
    {
        $this->viewableLens($request, $authorization);

        return $this->call(ActionController::class, 'form', ['resource' => $resource, 'action' => $action]);
    }

    public function dependencies(LatheRequest $request, ResourceAuthorization $authorization): JsonResponse
    {
        $this->viewableLens($request, $authorization);

        return $this->call(ActionLookupController::class, 'dependencies');
    }

    public function associatable(LatheRequest $request, ResourceAuthorization $authorization): JsonResponse
    {
        $this->viewableLens($request, $authorization);

        return $this->call(ActionLookupController::class, 'associatable');
    }

    public function run(LatheRequest $request, ResourceAuthorization $authorization): JsonResponse
    {
        $this->throughLens($request, $authorization);

        return $this->call(ActionRunController::class, '__invoke');
    }

    public function batchDelete(LatheRequest $request, ResourceAuthorization $authorization): JsonResponse
    {
        $this->throughLens($request, $authorization);

        return $this->call(BatchDeleteController::class, 'delete');
    }

    public function batchForceDelete(LatheRequest $request, ResourceAuthorization $authorization): JsonResponse
    {
        $this->throughLens($request, $authorization);

        return $this->call(BatchDeleteController::class, 'forceDelete');
    }

    public function batchRestore(LatheRequest $request, ResourceAuthorization $authorization): JsonResponse
    {
        $this->throughLens($request, $authorization);

        return $this->call(BatchDeleteController::class, 'restore');
    }

    /**
     * Checks the lens, then points the body's selection at it, so a run reads the lens's rows.
     */
    protected function throughLens(LatheRequest $request, ResourceAuthorization $authorization): void
    {
        $lens = $this->viewableLens($request, $authorization);

        if ($request->has('selection')) {
            $request->merge(['selection' => ['lens' => $lens->uriKey()] + $request->selection()->toArray()]);
        }
    }

    protected function viewableLens(LatheRequest $request, ResourceAuthorization $authorization): Lens
    {
        $class = $this->viewableResource($request, $authorization);
        $lens = collect($class::make()->lenses($request))->first(fn (Lens $lens) => $lens->uriKey() === $request->route('lens'));

        abort_if($lens === null, 404);
        abort_unless($lens->authorizedToSee($request), 403);
        abort_unless($authorization->allowsLens($request->user(), $class, $lens), 403);

        return $lens;
    }

    /**
     * @param  class-string  $controller
     * @param  array<string, mixed>  $parameters
     */
    protected function call(string $controller, string $method, array $parameters = []): JsonResponse
    {
        $response = app()->call($controller.'@'.$method, $parameters);

        return $response instanceof JsonResponse ? $response : new JsonResponse($response);
    }
}
