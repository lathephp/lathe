<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Preferences\LayoutChoice;
use Lathe\Preferences\UserPreference;

/**
 * The layout the signed-in user last chose on a Resource's list, the table or the Board. The route names no user, so each user writes only their own row.
 */
class LayoutPreferenceController
{
    use AuthorizesResources;

    public function update(LatheRequest $request, ResourceAuthorization $authorization): Response
    {
        $class = $this->viewableResource($request, $authorization);

        // Only a list with a Board has a layout to choose.
        abort_if($class::make()->definition()->getBoard() === null, 404);

        $data = Validator::make($request->all(), ['layout' => ['required', 'string', Rule::in(LayoutChoice::LAYOUTS)]])->validate();

        UserPreference::updateOrCreate(
            ['user_id' => UserPreference::userId($request), 'key' => LayoutChoice::key($class::uriKey())],
            ['value' => ['layout' => $data['layout']]],
        );

        return new Response(null, 204);
    }
}
