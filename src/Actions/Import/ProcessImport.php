<?php

namespace Lathe\Actions\Import;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Lathe\Actions\Export\ExportWriter;
use Lathe\Lathe;
use Lathe\Support\ConfigStore;
use Lathe\Support\JobContext;
use LogicException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer;
use Throwable;

/**
 * Runs an import, dry or real, over its file one chunk at a time. Each chunk looks up its
 * keys and related records with one query per column, then saves its rows together with the
 * checkpoint, so a stopped run resumes at the row after the last saved chunk.
 *
 * Within a row the hooks run in order. Across a chunk they run in three passes: every row is
 * mapped, hooked and cast before the related records are looked up, and every row is
 * validated before the keys are looked up, so each lookup sees the whole chunk.
 */
class ProcessImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?int $tries = null;

    public ?int $timeout = null;

    /**
     * Keys a dry run has counted, by key column, since the table never receives them.
     *
     * @var array<string, array<string, true>>
     */
    protected array $seen = [];

    public function __construct(public Import $import, public ImportAction $action, public JobContext $context)
    {
        $this->onConnection($action->connection);
        $this->onQueue($action->queue);
        $this->tries = $action->tries;
        $this->timeout = $action->timeout;
    }

    public function handle(): void
    {
        $this->context->restore();
        $import = $this->import->refresh();
        $import->update(['status' => 'processing', 'exception' => null]);

        try {
            $this->run($import);
        } catch (Throwable $exception) {
            // The chunk that threw rolled back, so the row holds the last saved checkpoint.
            $import->refresh();
            $import->update(['status' => 'failed', 'exception' => $exception->getMessage()]);
            $this->notify($import);

            throw $exception;
        }
    }

    /**
     * The queue gave up on the job: a timeout, a lost worker or the last failed try. A throw
     * inside handle() has marked the import already; anything else would leave it processing
     * for good, so it is marked failed here and can be resumed.
     */
    public function failed(?Throwable $exception): void
    {
        $import = $this->import->fresh();

        if ($import === null || $import->status === 'failed') {
            return;
        }

        $this->context->restore();
        $import->update(['status' => 'failed', 'exception' => $exception?->getMessage() ?? Lathe::__('The import stopped before it finished.')]);
        $this->notify($import);
    }

    protected function run(Import $import): void
    {
        $config = app(ConfigStore::class);
        $chunkSize = max(1, $this->action->getChunkSize() ?? $config->integer('imports.chunk_size'));
        $reader = new ImportReader(Storage::disk($import->file_disk)->path($import->file_name));
        $this->seen = [];

        if ($import->total_rows === 0) {
            $import->update(['total_rows' => $reader->count()]);
        }

        $chunk = [];

        foreach ($reader->rows() as $number => $cells) {
            if ($number <= $import->last_processed_row) {
                continue;
            }

            $chunk[$number] = $cells;

            if (count($chunk) >= $chunkSize) {
                $this->processChunk($import, $chunk);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            $this->processChunk($import, $chunk);
        }

        if ($import->failed_rows > 0) {
            $import->error_file = $this->writeErrorFile($import);
        }

        $import->status = 'completed';
        $import->completed_at = now();
        $import->save();

        $this->notify($import);
    }

    /**
     * @param  non-empty-array<int, list<mixed>>  $chunk  cells by row number
     */
    protected function processChunk(Import $import, array $chunk): void
    {
        $columns = $this->columns();
        $caster = new RowCaster($this->context->timezone);
        $mapped = [];
        $rows = [];
        $typed = [];
        $failed = [];

        foreach ($chunk as $number => $cells) {
            $mapped[$number] = $this->map($import, $cells);

            try {
                $row = $mapped[$number];
                $result = $this->action->callHook('beforeRow', $row, $number);

                if ($result === false) {
                    $import->skipped_rows++;

                    continue;
                }

                [$row, $typed[$number], $errors] = $this->cast($columns, is_array($result) ? $this->row($result) : $row, $caster);

                if ($errors !== []) {
                    $failed[$number] = $errors;

                    continue;
                }

                $rows[$number] = $row;
            } catch (Throwable $exception) {
                $failed[$number] = ['_exception' => [$exception->getMessage()]];
            }
        }

        $rows = $this->resolveRelationships($columns, $rows);

        foreach ($rows as $number => $row) {
            try {
                $row = $this->hooked('beforeValidate', $row, $number);
                $validator = Validator::make($this->asTyped($row, $typed[$number] ?? []), $this->rules($columns), $this->messages($columns));

                if ($validator->fails()) {
                    /** @var array<string, list<string>> $errors */
                    $errors = $validator->errors()->toArray();
                    $failed[$number] = $errors;
                    unset($rows[$number]);

                    continue;
                }

                $rows[$number] = $this->hooked('afterValidate', $row, $number);
            } catch (Throwable $exception) {
                $failed[$number] = ['_exception' => [$exception->getMessage()]];
                unset($rows[$number]);
            }
        }

        $found = $this->findKeys($rows);

        $import->getConnection()->transaction(function () use ($import, $chunk, $rows, $found, $mapped, $failed) {
            foreach ($rows as $number => $row) {
                try {
                    $this->saveRow($import, $row, $number, $found);
                } catch (Throwable $exception) {
                    $failed[$number] = ['_exception' => [$exception->getMessage()]];
                }
            }

            ksort($failed);
            $this->recordFailures($import, $failed, $mapped);

            $import->failed_rows += count($failed);
            $import->processed_rows += count($chunk);
            $import->last_processed_row = (int) array_key_last($chunk);
            $import->save();
        });
    }

    /**
     * The duplicate check and, unless this is a dry run, the save hooks and the save.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, Model|true>>  $found
     */
    protected function saveRow(Import $import, array $row, int $number, array &$found): void
    {
        $skipBy = $this->action->getSkipDuplicates();

        if ($skipBy !== null && $this->known($found, $skipBy, $row) !== null) {
            $import->skipped_rows++;

            return;
        }

        $upsertBy = $this->action->getUpsertBy();
        $existing = $upsertBy !== null ? $this->known($found, $upsertBy, $row) : null;

        if ($import->mode === 'dry_run') {
            $this->remember($found, $row, true);
            $existing !== null ? $import->updated_rows++ : $import->created_rows++;

            return;
        }

        $model = $existing instanceof Model ? $existing : $this->newModel();

        $model->getConnection()->transaction(function () use ($model, $row, $number) {
            $saved = $this->hooked('beforeSave', $row, $number);
            $model->fill(array_filter($saved, fn (string $key) => $model->isFillable($key), ARRAY_FILTER_USE_KEY))->save();
            $saved = $this->hooked('afterSave', $saved, $number);
            $this->action->callHook('afterRow', $saved, $number);
        });

        $this->remember($found, $row, $model);
        $existing !== null ? $import->updated_rows++ : $import->created_rows++;
    }

    /**
     * The rows already in the table, or seen earlier in a dry run, for each key column.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array<string, Model|true>>
     */
    protected function findKeys(array $rows): array
    {
        $found = [];

        foreach (array_unique(array_filter([$this->action->getUpsertBy(), $this->action->getSkipDuplicates()])) as $key) {
            $values = $this->values($rows, $key);
            $found[$key] = $this->seen[$key] ?? [];

            if ($values !== []) {
                foreach ($this->newModel()->whereIn($key, $values)->get() as $model) {
                    $found[$key][$this->keyString($model->getAttribute($key))] = $model;
                }
            }
        }

        return $found;
    }

    /**
     * @param  array<string, array<string, Model|true>>  $found
     * @param  array<string, mixed>  $row
     */
    protected function known(array $found, string $key, array $row): Model|true|null
    {
        $value = $row[$key] ?? null;

        return $value === null ? null : ($found[$key][$this->keyString($value)] ?? null);
    }

    /**
     * Note the row's keys, so a later row with the same key updates or skips.
     *
     * @param  array<string, array<string, Model|true>>  $found
     * @param  array<string, mixed>  $row
     */
    protected function remember(array &$found, array $row, Model|true $model): void
    {
        foreach (array_keys($found) as $key) {
            $value = $row[$key] ?? null;

            if ($value !== null) {
                $found[$key][$this->keyString($value)] = $model;

                if ($model === true) {
                    $this->seen[$key][$this->keyString($value)] = true;
                }
            }
        }
    }

    /**
     * Replace each relationship cell with the related key, one query per column.
     *
     * @param  array<string, ImportColumn>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function resolveRelationships(array $columns, array $rows): array
    {
        foreach ($columns as $name => $column) {
            $relationship = $column->getRelationship();
            $values = $relationship === null ? [] : $this->values($rows, $name);

            if ($relationship === null || $values === []) {
                continue;
            }

            $relation = $this->newModel()->{$relationship['name']}();

            if (! $relation instanceof Relation) {
                throw new LogicException("The import column [{$name}] names [{$relationship['name']}], which is not a relationship.");
            }

            $related = $relation->getRelated();
            $ownerKey = $relation instanceof BelongsTo ? $relation->getOwnerKeyName() : $related->getKeyName();
            $ids = [];

            foreach ($related->whereIn($relationship['resolveBy'], $values)->get([$ownerKey, $relationship['resolveBy']]) as $record) {
                $ids[$this->keyString($record->getAttribute($relationship['resolveBy']))] = $record->getAttribute($ownerKey);
            }

            foreach ($rows as $number => $row) {
                if (isset($row[$name])) {
                    $rows[$number][$name] = $ids[$this->keyString($row[$name])] ?? null;
                }
            }
        }

        return $rows;
    }

    /**
     * Only mapped columns exist in the row; an unmapped column is absent, never null.
     *
     * @param  list<mixed>  $cells
     * @return array<string, mixed>
     */
    protected function map(Import $import, array $cells): array
    {
        $row = [];

        foreach ($this->columnMap($import) as $index => $name) {
            $row[$name] = $cells[$index] ?? null;
        }

        return $row;
    }

    /**
     * @return array<int, string> header index => column name, known columns only
     */
    protected function columnMap(Import $import): array
    {
        $columns = $this->columns();
        $map = [];

        foreach ($import->column_map ?? [] as $index => $name) {
            if (is_string($name) && isset($columns[$name])) {
                $map[(int) $index] = $name;
            }
        }

        return $map;
    }

    /**
     * Cast the present cells. A cell the caster emptied keeps its typed text for validation,
     * so "abc" in a number column reads "must be a number", not "is required".
     *
     * @param  array<string, ImportColumn>  $columns
     * @param  array<string, mixed>  $row
     * @return array{array<string, mixed>, array<string, string>, array<string, list<string>>}
     */
    protected function cast(array $columns, array $row, RowCaster $caster): array
    {
        $typed = [];
        $errors = [];

        foreach ($columns as $name => $column) {
            if (! array_key_exists($name, $row)) {
                continue;
            }

            $original = $row[$name];

            try {
                $row[$name] = $caster->cast($column, $original);
            } catch (RowCastException $exception) {
                $errors[$name] = [$exception->getMessage()];

                continue;
            }

            if ($row[$name] === null && is_string($original) && trim($original) !== '' && $column->getRelationship() === null) {
                $typed[$name] = $original;
            }
        }

        return [$row, $typed, $errors];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $typed
     * @return array<string, mixed>
     */
    protected function asTyped(array $row, array $typed): array
    {
        foreach ($typed as $name => $text) {
            if (array_key_exists($name, $row) && $row[$name] === null) {
                $row[$name] = $text;
            }
        }

        return $row;
    }

    /**
     * Run a hook; an array answer replaces the row.
     *
     * @param  'beforeValidate'|'afterValidate'|'beforeSave'|'afterSave'  $hook
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function hooked(string $hook, array $row, int $number): array
    {
        $result = $this->action->callHook($hook, $row, $number);

        return is_array($result) ? $this->row($result) : $row;
    }

    /**
     * @param  array<mixed>  $result
     * @return array<string, mixed>
     */
    protected function row(array $result): array
    {
        $row = [];

        foreach ($result as $key => $value) {
            $row[(string) $key] = $value;
        }

        return $row;
    }

    /**
     * @param  array<int, array<string, list<string>>>  $failed
     * @param  array<int, array<string, mixed>>  $mapped
     */
    protected function recordFailures(Import $import, array $failed, array $mapped): void
    {
        $now = now();
        $records = [];

        foreach ($failed as $number => $errors) {
            $records[] = [
                'import_id' => $import->id,
                'row_number' => $number,
                'data' => json_encode($mapped[$number] ?? [], JSON_THROW_ON_ERROR),
                'errors' => json_encode($errors, JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($records, 100) as $batch) {
            FailedImportRow::insert($batch);
        }
    }

    /**
     * The failed rows as `_row_number`, the mapped columns and `_errors`.
     */
    protected function writeErrorFile(Import $import): string
    {
        $disk = Storage::disk($import->file_disk);
        $directory = trim(app(ConfigStore::class)->string('imports.directory'), '/');
        $file = "{$directory}/errors-{$import->id}.csv";
        $names = array_values(array_unique($this->columnMap($import)));

        $disk->makeDirectory($directory);
        $writer = new Writer;
        $writer->openToFile($disk->path($file));
        $writer->addRow(Row::fromValues(['_row_number', ...$names, '_errors']));

        foreach ($import->failedRows()->orderBy('row_number')->lazyById() as $failed) {
            $cells = array_map(fn (string $name) => $this->cell($failed->data[$name] ?? null), $names);
            $errors = array_map(
                fn (array $messages, string $column) => $column.': '.implode('; ', $messages),
                $failed->errors,
                array_keys($failed->errors),
            );

            $writer->addRow(Row::fromValues([$failed->row_number, ...array_map(fn (mixed $cell) => is_string($cell) ? ExportWriter::csvSafe($cell) : $cell, [...$cells, implode(' | ', $errors)])]));
        }

        $writer->close();

        return $file;
    }

    protected function cell(mixed $value): string|int|float|bool|null
    {
        return is_scalar($value) || $value === null ? $value : (json_encode($value) ?: null);
    }

    protected function notify(Import $import): void
    {
        if ($import->mode !== 'import' || ! app(ConfigStore::class)->boolean('notifications.enabled')) {
            return;
        }

        $user = Auth::guard($this->context->guard)->user();

        if ($user !== null) {
            Notification::send($user, new ImportComplete($import));
        }
    }

    /**
     * @return array<string, ImportColumn>
     */
    protected function columns(): array
    {
        $columns = [];

        foreach ($this->action->getColumns() as $column) {
            $columns[$column->getName()] = $column;
        }

        return $columns;
    }

    /**
     * @param  array<string, ImportColumn>  $columns
     * @return array<string, list<string>>
     */
    protected function rules(array $columns): array
    {
        return array_filter(array_map(fn (ImportColumn $column) => $column->getRules(), $columns));
    }

    /**
     * @param  array<string, ImportColumn>  $columns
     * @return array<string, string>
     */
    protected function messages(array $columns): array
    {
        $messages = [];

        foreach ($columns as $name => $column) {
            foreach ($column->getMessages() as $rule => $message) {
                $messages["{$name}.{$rule}"] = $message;
            }
        }

        return $messages;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<mixed>
     */
    protected function values(array $rows, string $column): array
    {
        $values = [];

        foreach ($rows as $row) {
            if (isset($row[$column])) {
                $values[$this->keyString($row[$column])] = $row[$column];
            }
        }

        return array_values($values);
    }

    protected function keyString(mixed $value): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : (json_encode($value) ?: '');
    }

    /**
     * The row's model_class, set from the action's model when the file was uploaded.
     */
    protected function newModel(): Model
    {
        $class = $this->import->model_class;

        return new $class;
    }
}
