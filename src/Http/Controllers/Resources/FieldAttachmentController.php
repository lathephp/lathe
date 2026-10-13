<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\FieldContext;
use Lathe\Field\MarkdownField;
use Lathe\Field\TiptapField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;
use Lathe\Uploads\FieldAttachment;
use Lathe\Uploads\UploadHandler;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Images of a Markdown or Tiptap field with files: the draft id an editor uploads under,
 * storing a finished upload where the editor can show it, and the deletes.
 */
class FieldAttachmentController
{
    use AuthorizesResources;

    public function __construct(protected UploadHandler $uploads)
    {
        //
    }

    /**
     * A new draft id. The uploads made under it belong to their uploader, so the id needs no record.
     */
    public function draftId(LatheRequest $request, ResourceAuthorization $authorization, string $resource, string $field): JsonResponse
    {
        $this->editorField($request, $authorization, $field);

        return new JsonResponse(['draft_id' => (string) Str::uuid()]);
    }

    /**
     * Store the caller's finished upload of this draft field on the field's disk and answer its URL.
     */
    public function store(LatheRequest $request, ResourceAuthorization $authorization, string $resource, string $field): JsonResponse
    {
        $target = $this->editorField($request, $authorization, $field);
        $request->validate(['upload_id' => ['required', 'string']]);

        $upload = $this->uploads->attachable($request->user(), $request->string('upload_id')->toString(), $target->attribute());

        if ($upload === null || $upload->draft_id === null) {
            throw new NotFoundHttpException;
        }

        return new JsonResponse(['url' => $this->uploads->attachToDraft($upload, $target->getAttachmentDisk())]);
    }

    /**
     * Delete one attachment, file and row. Its record comes through the scoped query and
     * must be one the caller may update.
     */
    public function destroy(LatheRequest $request, ResourceAuthorization $authorization, string $resource, string $field): Response
    {
        $class = $this->viewableResource($request, $authorization);
        $request->validate(['attachment_id' => ['required', 'integer']]);

        $attachment = FieldAttachment::where('attachable_type', $class::newModel()->getMorphClass())->find($request->integer('attachment_id'));

        if ($attachment === null) {
            throw new NotFoundHttpException;
        }

        $model = (new ResourceQuery($class::make(), $request, QueryContext::Edit))->find($attachment->attachable_id);

        abort_unless($authorization->allows($request->user(), $class, 'update', $model), 403, $authorization->denialMessage($class));
        $this->findEditorField($request, $class, $field, $model);

        $attachment->purge();

        return new Response(status: 204);
    }

    /**
     * Remove the caller's uploads of a draft the form gave up; other users' uploads stay.
     */
    public function destroyDraft(LatheRequest $request, ResourceAuthorization $authorization, string $resource, string $field, string $draftId): Response
    {
        $target = $this->editorField($request, $authorization, $field);

        $this->uploads->discardDraft($request->user(), $draftId, $target->attribute());

        return new Response(status: 204);
    }

    /**
     * The field from the create form, or from the edit form of the record a resource_id names,
     * with the create or update ability the form needs.
     */
    protected function editorField(LatheRequest $request, ResourceAuthorization $authorization, string $field): MarkdownField|TiptapField
    {
        $class = $this->viewableResource($request, $authorization);
        $id = $request->input('resource_id');
        $model = is_string($id) || is_int($id)
            ? (new ResourceQuery($class::make(), $request, QueryContext::Edit))->find($id)
            : null;

        abort_unless($authorization->allows($request->user(), $class, $model === null ? 'create' : 'update', $model), 403, $authorization->denialMessage($class));

        return $this->findEditorField($request, $class, $field, $model);
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>  $class
     */
    protected function findEditorField(LatheRequest $request, string $class, string $field, ?Model $model): MarkdownField|TiptapField
    {
        $context = $model === null ? FieldContext::Create : FieldContext::Update;
        $target = $class::make($model)->fieldsFor($context, $request)
            ->forContext($context, $request, $model)
            ->authorized($request)
            ->findByAttribute($field);

        if (($target instanceof MarkdownField || $target instanceof TiptapField) && $target->hasFiles()) {
            return $target;
        }

        throw new NotFoundHttpException;
    }
}
