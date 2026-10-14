<?php

namespace Lathe\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Lathe\Actions\Import\Import;
use Lathe\Actions\Import\ImportAction;
use Lathe\Actions\Import\ProcessImport;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\ResourceRegistry;
use Lathe\Support\JobContext;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The status, resume and error download of an import, for the user who started it.
 */
class ImportController
{
    public function show(LatheRequest $request, string $import): JsonResponse
    {
        return new JsonResponse($this->owned($request, $import)->toStatusArray());
    }

    /**
     * Dispatch a failed import again; it continues after its last saved chunk.
     */
    public function resume(LatheRequest $request, ResourceRegistry $resources, string $import): JsonResponse
    {
        $run = $this->owned($request, $import);

        abort_unless($run->status === 'failed', 422, Lathe::__('Only a failed import can be resumed.'));

        $resource = $resources->forModel($run->model_class);
        $action = $resource === null ? null : $resource::make()->availableActions($request)
            ->first(fn (mixed $action) => $action instanceof ImportAction);

        abort_unless($action instanceof ImportAction, 404);

        $run->update(['status' => 'queued']);
        Bus::dispatch(new ProcessImport($run, $action->forResource($resource), JobContext::capture($request)));

        return new JsonResponse(['status' => 'queued'], 202);
    }

    /**
     * The route is signed; another user gets 403 like a bad signature does.
     */
    public function downloadErrors(LatheRequest $request, string $import): StreamedResponse
    {
        $run = Import::find($import);
        $userId = $request->user()?->getAuthIdentifier();

        abort_unless($run !== null, 404);
        abort_unless($run->ownedBy(is_int($userId) || is_string($userId) ? $userId : null), 403);
        abort_unless($run->error_file !== null && Storage::disk($run->file_disk)->exists($run->error_file), 404);

        return Storage::disk($run->file_disk)->download($run->error_file, basename($run->error_file));
    }

    protected function owned(LatheRequest $request, string $id): Import
    {
        $run = Import::find($id);
        $userId = $request->user()?->getAuthIdentifier();

        abort_unless($run !== null && $run->ownedBy(is_int($userId) || is_string($userId) ? $userId : null), 404);

        return $run;
    }
}
