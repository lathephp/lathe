<?php

namespace Lathe\Actions\Import;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lathe\Actions\ActionResult;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Support\ConfigStore;
use Lathe\Support\JobContext;
use Lathe\Uploads\UploadHandler;
use LogicException;

/**
 * The steps of the import modal, each a POST /action with fields.import_step: `upload` stores
 * the chunked upload and answers its headers, `dry_run` and `import` queue a run of it. The
 * client names the upload and the import by id only, never a path or a disk.
 *
 * @phpstan-type StepResponse array{result: ActionResult, permitted: int, skipped: int, failed: int, errors: list<array{id: int|string|null, message: string}>, import_run?: array{id: int}}
 */
class ImportSteps
{
    public function __construct(protected UploadHandler $uploads, protected ConfigStore $config, protected ResourceAuthorization $authorization)
    {
        //
    }

    /**
     * @return StepResponse
     */
    public function handle(LatheRequest $request, ImportAction $action): array
    {
        $class = $request->resourceOrFail();

        abort_unless($this->authorization->allows($request->user(), $class, $action->ability()), 403, $this->authorization->denialMessage($class));

        $request->validate(['fields.import_step' => ['required', Rule::in(['upload', 'dry_run', 'import'])]]);

        return match ($request->input('fields.import_step')) {
            'upload' => $this->response($this->upload($request, $action)),
            'dry_run' => $this->run($request, $action, 'dry_run'),
            default => $this->run($request, $action, 'import'),
        };
    }

    /**
     * Move the user's complete upload to the imports disk and read its header and first rows.
     */
    protected function upload(LatheRequest $request, ImportAction $action): ActionResult
    {
        $id = $request->input('fields.file');
        $field = 'actions/'.$action->uriKey().'/'.ImportAction::FILE_FIELD;
        $upload = is_string($id) ? $this->uploads->attachable($request->user(), $id, $field) : null;

        abort_if($upload === null, 404);

        $disk = $this->config->string('imports.disk');
        $extension = Str::lower(pathinfo($upload->original_name, PATHINFO_EXTENSION));
        $path = Storage::disk($disk)->putFileAs($this->config->string('imports.directory'), $this->uploads->file($upload), Str::ulid().'.'.$extension);

        if (! is_string($path)) {
            throw new LogicException("The import upload [{$upload->id}] was not stored.");
        }

        $this->uploads->cancel($upload);
        $read = (new ImportReader(Storage::disk($disk)->path($path)))->preview();

        if ($read['headers'] === []) {
            Storage::disk($disk)->delete($path);

            return ActionResult::danger(Lathe::__('The file is empty and has no headers.'));
        }

        $import = Import::create([
            'user_id' => $this->uploads->ownerId($request->user()),
            'file_disk' => $disk,
            'file_name' => $path,
            'original_name' => $upload->original_name,
            'model_class' => $action->getModel(),
            'status' => 'uploaded',
        ]);

        return ActionResult::emit('import:headers-detected', [
            'import_id' => $import->id,
            'original_name' => $import->original_name,
            'headers' => $read['headers'],
            'preview' => $read['preview'],
            'columns' => $action->getColumns(),
            'auto_mapping' => ColumnMapper::autoMap($read['headers'], $action->getColumns()),
        ]);
    }

    /**
     * Store the column map and queue the dry run or the real import on the user's Import row.
     * Another user's import, or one of another model, answers 404 as if it did not exist.
     *
     * @param  'dry_run'|'import'  $mode
     * @return StepResponse
     */
    protected function run(LatheRequest $request, ImportAction $action, string $mode): array
    {
        $names = array_map(fn (ImportColumn $column) => $column->getName(), $action->getColumns());

        $request->validate([
            'fields.import_id' => ['required', 'integer'],
            'fields.column_map' => ['required', 'array'],
            'fields.column_map.*' => ['nullable', 'string', Rule::in($names)],
        ]);

        $import = Import::find($request->integer('fields.import_id'));

        abort_unless($import !== null && $import->ownedBy($this->uploads->ownerId($request->user())) && $import->model_class === $action->getModel(), 404);

        $map = array_map(fn (mixed $name) => is_string($name) ? $name : null, $request->array('fields.column_map'));
        $import->update(['column_map' => $map]);
        $action->start($import, $mode, JobContext::capture($request));

        $result = ActionResult::message($mode === 'import' ? Lathe::__('Import started.') : Lathe::__('Validation started.'));

        return [...$this->response($result), 'import_run' => ['id' => $import->id]];
    }

    /**
     * @return StepResponse
     */
    protected function response(ActionResult $result): array
    {
        return ['result' => $result, 'permitted' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];
    }
}
