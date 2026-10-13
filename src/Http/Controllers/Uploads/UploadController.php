<?php

namespace Lathe\Http\Controllers\Uploads;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Uploads\UploadHandler;

/**
 * Chunks, resume status, complete and cancel of the user's own upload. Another user's
 * upload id answers 404 on every verb.
 */
class UploadController
{
    public function __construct(protected UploadHandler $uploads)
    {
        //
    }

    public function show(LatheRequest $request, string $upload): JsonResponse
    {
        $pending = $this->uploads->owned($request->user(), $upload);

        return new JsonResponse(['size' => $pending->size, 'received' => $this->uploads->received($pending)]);
    }

    public function chunk(LatheRequest $request, string $upload, string $index): Response
    {
        $this->uploads->storeChunk($this->uploads->owned($request->user(), $upload), (int) $index, $request->getContent());

        return new Response(status: 204);
    }

    public function complete(LatheRequest $request, string $upload): JsonResponse
    {
        $pending = $this->uploads->owned($request->user(), $upload);
        $this->uploads->complete($pending);

        return new JsonResponse(['upload_id' => $pending->id, 'name' => $pending->original_name, 'size' => $pending->size]);
    }

    public function destroy(LatheRequest $request, string $upload): Response
    {
        $this->uploads->cancel($this->uploads->owned($request->user(), $upload));

        return new Response(status: 204);
    }
}
