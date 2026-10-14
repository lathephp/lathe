<?php

namespace Lathe\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;
use Lathe\Actions\BulkRun;
use Lathe\Enums\ActionEventStatus;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;

/**
 * The progress of a queued bulk run and its cancel, for the user who started it only;
 * anyone else gets 404, as for a run that does not exist.
 */
class BulkRunController
{
    public function show(LatheRequest $request, string $id): JsonResponse
    {
        return new JsonResponse($this->status($this->owned($request, $id)));
    }

    /**
     * Cancels the run: the chunk that runs now ends, later chunks skip, and the events still
     * waiting fail with "Cancelled".
     */
    public function destroy(LatheRequest $request, string $id): JsonResponse
    {
        $run = $this->owned($request, $id);

        if (in_array($run->status, ['waiting', 'running'], true)) {
            if ($run->batch_id !== null) {
                Bus::findBatch($run->batch_id)?->cancel();
                Lathe::actionEventModel()::forBatch($run->batch_id)
                    ->where('status', ActionEventStatus::Waiting->value)
                    ->update(['status' => ActionEventStatus::Failed->value, 'exception' => 'Cancelled', 'updated_at' => now()]);
            }

            $run->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        }

        return new JsonResponse($this->status($run));
    }

    protected function owned(LatheRequest $request, string $id): BulkRun
    {
        $run = BulkRun::find($id);
        $userId = $request->user()?->getAuthIdentifier();

        abort_unless($run instanceof BulkRun && $run->ownedBy(is_int($userId) || is_string($userId) ? $userId : null), 404);

        return $run;
    }

    /**
     * @return array{status: string, total: int, processed: int, failed: int, skipped: int}
     */
    protected function status(BulkRun $run): array
    {
        return ['status' => $run->status, 'total' => $run->total, 'processed' => $run->processed, 'failed' => $run->failed, 'skipped' => $run->skipped];
    }
}
