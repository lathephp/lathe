<?php

namespace Lathe\Http\Controllers\Uploads;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\FieldContext;
use Lathe\Field\FileField;
use Lathe\Field\RepeaterField;
use Lathe\Http\Controllers\Resources\AuthorizesResources;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;
use Lathe\Support\ConfigStore;
use Lathe\Uploads\UploadHandler;

/**
 * Opens an upload. The disk and directory come from the field definition or the config,
 * so a disk or directory in the body is never read.
 */
class StartUploadController
{
    use AuthorizesResources;

    public function __construct(protected UploadHandler $uploads, protected ConfigStore $config)
    {
        //
    }

    /**
     * An upload to a Resource's file field. With a resource_id the field comes from the Update
     * fields of that record, found through the scoped query; without one, from the Create fields.
     * The pending upload names the bare sub-field attribute, since rows move before the save.
     */
    public function field(LatheRequest $request, ResourceAuthorization $authorization, string $resource, string $field): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);
        $id = $request->input('resource_id');
        $model = is_string($id) || is_int($id)
            ? (new ResourceQuery($class::make(), $request, QueryContext::Edit))->find($id)
            : null;

        abort_unless($authorization->allows($request->user(), $class, $model === null ? 'create' : 'update', $model), 403, $authorization->denialMessage($class));

        $context = $model === null ? FieldContext::Create : FieldContext::Update;
        $target = $class::make($model)->fieldsFor($context, $request)
            ->forContext($context, $request, $model)
            ->authorized($request)
            ->findByAttribute(Str::before($field, '.'));

        // A dotted key such as items.0.photo names a file sub-field of a repeater row.
        if (str_contains($field, '.')) {
            $target = $target instanceof RepeaterField ? $target->findSubField(Str::after($field, '.')) : null;
        }

        abort_unless($target instanceof FileField, 404);

        return $this->start($request, $target->attribute(), $target->getStorageDisk(), $target->getStorageDir(), $target->getMaxSizeBytes(), $target);
    }

    /**
     * An upload for a custom field or a plugin: the storage_disk, in the uploads directory.
     */
    public function custom(LatheRequest $request): JsonResponse
    {
        $maxBytes = $this->config->integer('uploads.max_file_size_mb') * 1024 * 1024;

        return $this->start($request, '', $this->config->string('storage_disk'), 'uploads', $maxBytes);
    }

    protected function start(LatheRequest $request, string $field, string $disk, string $directory, int $maxBytes, ?FileField $target = null): JsonResponse
    {
        /** @var array{name: string, size: int, mime: string, draft_id?: string|null} $file */
        $file = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:'.$maxBytes],
            'mime' => ['required', 'string', 'max:255'],
            'draft_id' => ['nullable', 'string', 'max:255'],
        ])->validate();

        if ($target !== null && ! $target->accepts($file['name'], $file['mime'])) {
            throw ValidationException::withMessages(['mime' => 'The file type is not one of: '.implode(', ', $target->getAcceptedTypes()).'.']);
        }

        $upload = $this->uploads->start($request->user(), $field, $disk, $directory, $file);

        return new JsonResponse(['upload_id' => $upload->id, 'chunk_size' => $this->uploads->chunkSize()], 201);
    }
}
