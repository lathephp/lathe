<?php

namespace Lathe\Actions\Import;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Laravel\SerializableClosure\SerializableClosure;
use Lathe\Actions\Action;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Support\JobContext;
use LogicException;

/**
 * Turns an uploaded csv or xlsx file into rows of a model: the columns it accepts, the key
 * that updates or skips a row, and the hooks each row passes through. ProcessImport runs it.
 */
class ImportAction extends Action
{
    /** @var list<ImportColumn> */
    protected array $columns = [];

    /** @var class-string<Model>|null */
    protected ?string $model = null;

    /** @var class-string<\Lathe\Resources\Resource>|null */
    protected ?string $resourceClass = null;

    protected ?string $upsertBy = null;

    protected ?string $skipDuplicates = null;

    protected ?int $chunkSize = null;

    protected ?Closure $fieldsCallback = null;

    protected ?Closure $beforeRow = null;

    protected ?Closure $beforeValidate = null;

    protected ?Closure $afterValidate = null;

    protected ?Closure $beforeSave = null;

    protected ?Closure $afterSave = null;

    protected ?Closure $afterRow = null;

    public function __construct()
    {
        parent::__construct();

        $this->withName(Lathe::__('Import'))
            ->withUriKey('import')
            ->standalone()
            ->confirmable()
            ->withoutActionEvents();
    }

    /**
     * Queue a dry run or the real import of an uploaded file. A new run starts from the first
     * row with empty counters; the resume endpoint dispatches ProcessImport itself.
     *
     * @param  'dry_run'|'import'  $mode
     */
    public function start(Import $import, string $mode, JobContext $context): void
    {
        $import->failedRows()->delete();

        if ($import->error_file !== null) {
            Storage::disk($import->file_disk)->delete($import->error_file);
        }

        $import->update([
            'mode' => $mode, 'status' => 'queued', 'total_rows' => 0, 'processed_rows' => 0, 'last_processed_row' => 0,
            'created_rows' => 0, 'updated_rows' => 0, 'skipped_rows' => 0, 'failed_rows' => 0,
            'error_file' => null, 'exception' => null, 'completed_at' => null,
        ]);

        dispatch(new ProcessImport($import, $this, $context));
    }

    /**
     * @param  ImportColumn|list<ImportColumn>  $columns
     */
    public function withColumns(ImportColumn|array $columns, ImportColumn ...$more): static
    {
        $this->columns = [...(is_array($columns) ? $columns : [$columns]), ...array_values($more)];

        return $this;
    }

    /**
     * @param  class-string<Model>  $class
     */
    public function withModel(string $class): static
    {
        $this->model = $class;

        return $this;
    }

    /**
     * The Resource the action belongs to; its model is the default target.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $resourceClass
     */
    public function forResource(string $resourceClass): static
    {
        $this->resourceClass = $resourceClass;

        return $this;
    }

    /**
     * A row whose value of this column is in the table, or earlier in the file, updates that row.
     */
    public function upsertBy(string $column): static
    {
        $this->upsertBy = $column;

        return $this;
    }

    /**
     * A row whose value of this column is in the table, or earlier in the file, is skipped.
     */
    public function skipDuplicates(string $column): static
    {
        $this->skipDuplicates = $column;

        return $this;
    }

    public function queueable(?string $connection = null, ?string $queue = null, ?int $tries = null, ?int $timeout = null, ?int $chunkSize = null): static
    {
        $this->connection = $connection;
        $this->queue = $queue;
        $this->tries = $tries;
        $this->timeout = $timeout;
        $this->chunkSize = $chunkSize;

        return $this;
    }

    /**
     * Extra fields of the import modal.
     *
     * @param  Closure(LatheRequest): list<mixed>  $callback
     */
    public function withFields(Closure $callback): static
    {
        $this->fieldsCallback = $callback;

        return $this;
    }

    /**
     * Answering false skips the row; an array replaces it.
     *
     * @param  Closure(array<string, mixed>, int): mixed  $callback
     */
    public function beforeRow(Closure $callback): static
    {
        $this->beforeRow = $callback;

        return $this;
    }

    /**
     * @param  Closure(array<string, mixed>, int): mixed  $callback
     */
    public function beforeValidate(Closure $callback): static
    {
        $this->beforeValidate = $callback;

        return $this;
    }

    /**
     * @param  Closure(array<string, mixed>, int): mixed  $callback
     */
    public function afterValidate(Closure $callback): static
    {
        $this->afterValidate = $callback;

        return $this;
    }

    /**
     * Not run in a dry run.
     *
     * @param  Closure(array<string, mixed>, int): mixed  $callback
     */
    public function beforeSave(Closure $callback): static
    {
        $this->beforeSave = $callback;

        return $this;
    }

    /**
     * @param  Closure(array<string, mixed>, int): mixed  $callback
     */
    public function afterSave(Closure $callback): static
    {
        $this->afterSave = $callback;

        return $this;
    }

    /**
     * @param  Closure(array<string, mixed>, int): mixed  $callback
     */
    public function afterRow(Closure $callback): static
    {
        $this->afterRow = $callback;

        return $this;
    }

    /**
     * Run one hook on a row; null when the hook is not set.
     *
     * @param  'beforeRow'|'beforeValidate'|'afterValidate'|'beforeSave'|'afterSave'|'afterRow'  $hook
     * @param  array<string, mixed>  $row
     */
    public function callHook(string $hook, array $row, int $rowNumber): mixed
    {
        $callback = $this->{$hook};

        return $callback instanceof Closure ? $callback($row, $rowNumber) : null;
    }

    /**
     * The Resource ability a user needs to import.
     */
    public function ability(): string
    {
        return 'import';
    }

    public function fields(LatheRequest $request): array
    {
        $fields = $this->fieldsCallback === null ? [] : ($this->fieldsCallback)($request);

        return is_array($fields) ? array_values($fields) : [];
    }

    /**
     * @return list<ImportColumn>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @return class-string<Model>
     */
    public function getModel(): string
    {
        if ($this->model !== null) {
            return $this->model;
        }

        if ($this->resourceClass !== null) {
            return $this->resourceClass::model();
        }

        throw new LogicException(static::class.' has no model. Call withModel() or forResource().');
    }

    /**
     * @return class-string<\Lathe\Resources\Resource>|null
     */
    public function getResourceClass(): ?string
    {
        return $this->resourceClass;
    }

    public function getUpsertBy(): ?string
    {
        return $this->upsertBy;
    }

    public function getSkipDuplicates(): ?string
    {
        return $this->skipDuplicates;
    }

    public function getChunkSize(): ?int
    {
        return $this->chunkSize;
    }

    /**
     * Closures cannot be serialized, so a queued payload carries them wrapped.
     *
     * @return array<mixed>
     */
    public function __serialize(): array
    {
        $data = [];

        foreach (get_object_vars($this) as $property => $value) {
            $data[$property] = $value instanceof Closure ? new SerializableClosure($value) : $value;
        }

        return $data;
    }

    /**
     * @param  array<mixed>  $data
     */
    public function __unserialize(array $data): void
    {
        foreach ($data as $property => $value) {
            $this->{$property} = $value instanceof SerializableClosure ? $value->getClosure() : $value;
        }
    }
}
