<?php

namespace Lathe\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Lathe\Auth\Impersonation;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;

/**
 * Starts, reports and stops impersonation. The target is read through its Resource's scoped
 * query, so a user the Resource cannot reach (another tenant's, say) answers 404. The session
 * is read from the live Request: ServeLathe copies the LatheRequest before the session starts.
 */
class ImpersonateController
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['impersonating' => Lathe::impersonator()->impersonating($request)]);
    }

    /**
     * Starts impersonating the user the body names. While one runs, a second POST stops it.
     */
    public function store(Request $request, LatheRequest $latheRequest): JsonResponse
    {
        if (Lathe::impersonator()->impersonating($request)) {
            return $this->destroy($request);
        }

        $request->validate(['resource' => ['required', 'string'], 'resourceId' => ['required']]);
        $resource = $request->input('resource');
        $id = $request->input('resourceId');
        $class = is_string($resource) ? Lathe::resources()->forKey($resource) : null;

        abort_unless($class !== null && (is_string($id) || is_int($id)), 404);

        $target = (new ResourceQuery($class::make(), $latheRequest, QueryContext::Detail))->find($id);
        $user = $request->user(Lathe::fortify()->guard());

        abort_if(is_object($user) && Impersonation::same($user, $target), 403, Lathe::__('You cannot impersonate yourself.'));
        abort_unless($target instanceof Authenticatable && Impersonation::allows($user, $target), 403, Lathe::__('This action is unauthorized.'));

        Lathe::impersonator()->impersonate($request, $this->guard(), $target);

        return response()->json(['redirect' => Lathe::impersonator()->redirectAfterStartingImpersonation($request)]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $impersonator = Lathe::impersonator();
        // Read before stopping, which forgets the page the impersonation started from.
        $redirect = $impersonator->redirectAfterStoppingImpersonation($request);

        $impersonator->stopImpersonating($request, $this->guard(), Lathe::fortify()->userModel() ?? '');

        return response()->json(['redirect' => $redirect]);
    }

    protected function guard(): StatefulGuard
    {
        return Auth::guard(Lathe::fortify()->guard());
    }
}
