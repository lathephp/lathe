<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;
use Lathe\ActionEvents\ActionEventRecorder;
use Lathe\Actions\BulkRun;
use Lathe\Actions\Jobs\PlanBulkRun;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\SelectionResolver;
use Lathe\Resources\RecordDeleter;
use Lathe\Support\JobContext;

/**
 * Delete, force delete and restore every row of a selection, each row through the same
 * RecordDeleter as the single-row routes, and all the audit rows of the request under one batch id.
 * A row the write does not apply to, one the policy narrows out and one that throws count as skipped.
 * A selection over the Resource's inline limit goes to the queue as a bulk run instead.
 */
class BatchDeleteController
{
    use AuthorizesResources;

    public function delete(LatheRequest $request, ResourceAuthorization $authorization, RecordDeleter $deleter, ActionEventRecorder $recorder): JsonResponse
    {
        return $this->handle($request, $authorization, $deleter, $recorder, 'delete', 'deleted');
    }

    public function forceDelete(LatheRequest $request, ResourceAuthorization $authorization, RecordDeleter $deleter, ActionEventRecorder $recorder): JsonResponse
    {
        return $this->handle($request, $authorization, $deleter, $recorder, 'forceDelete', 'deleted');
    }

    public function restore(LatheRequest $request, ResourceAuthorization $authorization, RecordDeleter $deleter, ActionEventRecorder $recorder): JsonResponse
    {
        return $this->handle($request, $authorization, $deleter, $recorder, 'restore', 'restored');
    }

    /**
     * @param  'delete'|'forceDelete'|'restore'  $ability
     * @param  'deleted'|'restored'  $key
     */
    protected function handle(LatheRequest $request, ResourceAuthorization $authorization, RecordDeleter $deleter, ActionEventRecorder $recorder, string $ability, string $key): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);
        $resource = $class::make();

        // A model without soft deletes has nothing to restore.
        abort_if($ability === 'restore' && ! $resource->definition()->usesSoftDeletes(), 404);

        $selection = $request->selection();
        $resolver = new SelectionResolver($resource, $request, $selection);
        $resolver->ensureNotEmpty();

        $total = $resolver->query()->count();
        $user = $request->user();
        $any = $authorization->narrowsBulk($class, $ability);
        $rows = $resolver->lazy(200);

        if ($any) {
            $narrowed = $authorization->narrowBulk($user, $class, $ability, $resolver->query());
            abort_unless($narrowed->exists(), 403, $authorization->denialMessage($class));

            $model = $narrowed->getModel();
            $rows = $narrowed->lazyById(200, $model->getQualifiedKeyName(), $model->getKeyName());
        }

        if ($total > $resource->definition()->getBulkInlineMax()) {
            // Without {ability}Any the worker asks per row; the first chunk answers 403 here when no row of it is permitted.
            if (! $any) {
                $abilities = $authorization->abilitiesFor($user, $class, $rows->take(200)->collect(), [$ability]);
                abort_unless(in_array(true, array_column($abilities, $ability), true), 403, $authorization->denialMessage($class));
            }

            $run = BulkRun::begin($request, $class, $ability, $total);
            $run->dispatchBatch(Bus::batch([new PlanBulkRun($run->id, JobContext::capture($request), $class, $selection->toArray(), ability: $ability)]));

            return new JsonResponse([$key => 0, 'skipped' => 0, 'bulk_run' => ['id' => $run->id, 'total' => $total]]);
        }

        $totals = ['done' => 0, 'attempted' => 0, 'denied' => 0];

        $recorder->inBatch(function () use ($request, $deleter, $class, $ability, $any, $rows, &$totals): void {
            foreach ($rows->chunk(200) as $chunk) {
                foreach ($deleter->chunk($request, $class, $ability, $chunk->collect(), authorized: $any) as $name => $count) {
                    $totals[$name] += $count;
                }
            }
        });

        // No row of the selection was permitted, so nothing ran.
        abort_if($totals['attempted'] === 0 && $totals['denied'] > 0, 403, $authorization->denialMessage($class));

        return new JsonResponse([$key => $totals['done'], 'skipped' => $total - $totals['done']]);
    }
}
