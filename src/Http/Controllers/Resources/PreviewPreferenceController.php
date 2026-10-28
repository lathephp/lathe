<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Preferences\PreviewState;
use Lathe\Preferences\UserPreference;

/**
 * Whether the signed-in user has the preview panel open on a Resource's list. The route names no user, so each user writes only their own row.
 */
class PreviewPreferenceController
{
    use AuthorizesResources;

    public function update(LatheRequest $request, ResourceAuthorization $authorization): Response
    {
        $class = $this->viewableResource($request, $authorization);

        $data = Validator::make($request->all(), ['open' => ['required', 'boolean']])->validate();

        UserPreference::updateOrCreate(
            ['user_id' => UserPreference::userId($request), 'key' => PreviewState::key($class::uriKey())],
            ['value' => ['open' => (bool) $data['open']]],
        );

        return new Response(null, 204);
    }
}
