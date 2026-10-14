<?php

namespace Lathe\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Lathe\Actions\Export\Export;
use Lathe\Http\Requests\LatheRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The progress of an export and its file, for the user who started it only.
 */
class ExportController
{
    /**
     * Anyone but the owner gets 404, as for an export that does not exist.
     */
    public function show(LatheRequest $request, string $export): JsonResponse
    {
        $run = Export::find($export);

        abort_unless($run instanceof Export && $run->ownedBy($this->userId($request)), 404);

        return new JsonResponse($run->toStatusArray());
    }

    /**
     * The signature is checked before this runs. Another user gets 403; an export that has
     * not finished, or whose file is gone, 404.
     */
    public function download(LatheRequest $request, string $export): StreamedResponse
    {
        $run = Export::find($export);

        abort_unless($run instanceof Export, 404);
        abort_unless($run->ownedBy($this->userId($request)), 403);
        abort_if($run->completed_at === null || $run->file_name === null, 404);

        $disk = Storage::disk($run->file_disk);

        abort_unless($disk->exists($run->file_name), 404);

        return $disk->download($run->file_name, $run->downloadName());
    }

    protected function userId(LatheRequest $request): int|string|null
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) || is_string($id) ? $id : null;
    }
}
