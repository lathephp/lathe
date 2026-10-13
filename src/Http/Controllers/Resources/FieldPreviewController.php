<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\Contracts\PreviewsContent;
use Lathe\Field\FieldContext;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;

/**
 * Renders an editor's preview, such as Markdown, on the server. The field must be one of
 * the create or edit form's authorized fields that previews content.
 */
class FieldPreviewController
{
    use AuthorizesResources;

    /** The largest value a preview renders, in bytes. */
    public const int MAX_BYTES = 262_144;

    public function create(LatheRequest $request, ResourceAuthorization $authorization, string $resource, string $field): JsonResponse
    {
        return $this->preview($request, $authorization, $field);
    }

    public function update(LatheRequest $request, ResourceAuthorization $authorization, string $resource, string $resourceId, string $field): JsonResponse
    {
        return $this->preview($request, $authorization, $field, $resourceId);
    }

    protected function preview(LatheRequest $request, ResourceAuthorization $authorization, string $field, ?string $resourceId = null): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);
        $model = $resourceId === null ? null : (new ResourceQuery($class::make(), $request, QueryContext::Edit))->find($resourceId);

        abort_unless($authorization->allows($request->user(), $class, $model === null ? 'create' : 'update', $model), 403, $authorization->denialMessage($class));

        $context = $model === null ? FieldContext::Create : FieldContext::Update;
        $target = $class::make($model)->fieldsFor($context, $request)
            ->forContext($context, $request, $model)
            ->authorized($request)
            ->findByAttribute($field);

        abort_unless($target instanceof PreviewsContent, 404);

        $request->validate(['value' => ['nullable', 'string']]);
        $value = $request->string('value')->toString();

        // Bytes, not characters: Laravel's max rule would count a multibyte character once.
        if (strlen($value) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['value' => Lathe::__('The value may not be greater than :max bytes.', ['max' => self::MAX_BYTES])]);
        }

        return new JsonResponse(['preview' => $target->previewFor($value)]);
    }
}
