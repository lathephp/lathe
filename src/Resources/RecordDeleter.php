<?php

namespace Lathe\Resources;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Events\ResourceDeleted;
use Lathe\Events\ResourceForceDeleted;
use Lathe\Events\ResourceRestored;
use Lathe\Field\FieldContext;
use Lathe\Field\FileField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Uploads\FieldAttachment;
use Lathe\Widgets\WidgetCache;
use Throwable;

/**
 * Delete, force delete and restore one record. Each runs its before hook, the write and
 * its event in one transaction, then its after hook once the transaction commits. The
 * single-row routes and the batch routes both run a record through here.
 */
class RecordDeleter
{
    /**
     * @param  class-string<\Lathe\Resources\Resource>  $class
     */
    public function delete(LatheRequest $request, string $class, Model $model): void
    {
        $this->run($request, $class, $model, 'Delete', fn () => $model->delete(), ResourceDeleted::class, purge: true);
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>  $class
     */
    public function forceDelete(LatheRequest $request, string $class, Model $model): void
    {
        $this->run($request, $class, $model, 'ForceDelete', fn () => $model->forceDelete(), ResourceForceDeleted::class, purge: true);
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>  $class
     */
    public function restore(LatheRequest $request, string $class, Model $model): void
    {
        // A model without soft deletes has nothing to restore.
        assert(method_exists($model, 'restore'));

        $this->run($request, $class, $model, 'Restore', fn () => $model->restore(), ResourceRestored::class);
    }

    /**
     * One chunk of a batch, each row in its own transaction. A row the write does not apply to
     * (a delete of a trashed row, a restore of a live one) is left out, and a row that throws is
     * reported and left as it was. With $authorized false, the chunk's abilities are read in one go.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @param  'delete'|'forceDelete'|'restore'  $ability
     * @param  Collection<int, Model>  $models
     * @return array{done: int, attempted: int, denied: int} rows written, rows tried, rows the policy refused
     */
    public function chunk(LatheRequest $request, string $class, string $ability, Collection $models, bool $authorized = false): array
    {
        $applicable = $models->filter(fn (Model $model) => match ($ability) {
            'delete' => ! $this->trashed($model),
            'restore' => $this->trashed($model),
            default => true,
        })->values();

        $allowed = $applicable;

        if (! $authorized) {
            $abilities = app(ResourceAuthorization::class)->abilitiesFor($request->user(), $class, $applicable, [$ability]);
            $allowed = $applicable->filter(fn (Model $model) => $abilities[$this->keyOf($model)][$ability] ?? false);
        }

        $done = 0;

        foreach ($allowed as $model) {
            try {
                $this->{$ability}($request, $class, $model);
                $done++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return ['done' => $done, 'attempted' => $allowed->count(), 'denied' => $applicable->count() - $allowed->count()];
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @param  Closure(): mixed  $write
     * @param  class-string<ResourceDeleted|ResourceForceDeleted|ResourceRestored>  $event
     */
    protected function run(LatheRequest $request, string $class, Model $model, string $hook, Closure $write, string $event, bool $purge = false): void
    {
        $model->getConnection()->transaction(function () use ($request, $class, $model, $hook, $write, $event) {
            $class::{'before'.$hook}($request, $model);
            $write();
            event(new $event($model, $class));
        });

        app(WidgetCache::class)->flushTables([$model->getTable()]);
        $class::{'after'.$hook}($request, $model);

        // Files cannot roll back with the transaction, so they go only once the delete has committed.
        if ($purge) {
            $this->purgeAttachments($request, $model);
        }
    }

    /**
     * Removes the files of the record's prunable file fields and its editor attachments, after the delete commits.
     */
    protected function purgeAttachments(LatheRequest $request, Model $model): void
    {
        // A soft-deleted model still exists and keeps its files for a restore.
        if ($model->exists) {
            return;
        }

        foreach ($request->resourceOrFail()::make($model)->fieldsFor(FieldContext::Detail, $request) as $field) {
            if ($field instanceof FileField && $field->isPrunable()) {
                $field->deleteFile($request, $model);
            }
        }

        foreach (FieldAttachment::of($model)->get() as $attachment) {
            $attachment->purge();
        }
    }

    protected function trashed(Model $model): bool
    {
        return method_exists($model, 'trashed') && $model->trashed() === true;
    }

    protected function keyOf(Model $model): int|string
    {
        $key = $model->getKey();

        return is_int($key) || is_string($key) ? $key : (string) json_encode($key);
    }
}
