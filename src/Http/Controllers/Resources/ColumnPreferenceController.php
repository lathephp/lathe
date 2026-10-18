<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\Field;
use Lathe\Http\Payloads\IndexPayload;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Preferences\ColumnChoice;
use Lathe\Preferences\UserPreference;

/**
 * The signed-in user's columns of a Resource index. The route names no user, so each user writes only their own row.
 */
class ColumnPreferenceController
{
    use AuthorizesResources;

    public function update(LatheRequest $request, ResourceAuthorization $authorization, IndexPayload $payload): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);

        $data = Validator::make($request->all(), [
            'order' => ['present', 'list'],
            'order.*' => ['string'],
            'hidden' => ['present', 'list'],
            'hidden.*' => ['string'],
            'pinned' => ['present', 'list'],
            'pinned.*' => ['string'],
        ])->validate();

        // Cleaned against the same columns the index applies the choice to.
        $codeKeys = array_map(fn (Field $field) => $field->key(), $payload->columns($class::make()));
        $choice = ColumnChoice::age($codeKeys, $data);

        UserPreference::updateOrCreate(
            ['user_id' => UserPreference::userId($request), 'key' => ColumnChoice::key($class::uriKey())],
            ['value' => $choice],
        );

        return new JsonResponse($choice);
    }

    public function destroy(LatheRequest $request, ResourceAuthorization $authorization): Response
    {
        $class = $this->viewableResource($request, $authorization);

        UserPreference::where('user_id', UserPreference::userId($request))->where('key', ColumnChoice::key($class::uriKey()))->delete();

        return new Response(null, 204);
    }
}
