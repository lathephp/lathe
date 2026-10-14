<?php

namespace Lathe\Actions\Export;

use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lathe\Actions\Action;
use Lathe\Actions\ActionFields;
use Lathe\Actions\ActionFieldValidator;
use Lathe\Actions\ActionResult;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\CurrencyField;
use Lathe\Field\Field;
use Lathe\Http\Payloads\IndexPayload;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Query\Selection;
use Lathe\Query\SelectionResolver;
use Lathe\Resources\Resource;
use Lathe\Support\JobContext;
use RuntimeException;
use Throwable;

/**
 * Writes the selected rows to a CSV or XLSX file on exports.disk. Up to the Resource's
 * bulk_inline_max rows the file is written in the request and the result is a download;
 * above it ProcessExport writes it in the queue and the progress toast follows the run.
 */
class ExportAction extends Action
{
    /** The most data rows an XLSX sheet holds below its header. */
    public const int XLSX_MAX_ROWS = 1_048_575;

    /** Rows per read and per progress update; null reads exports.chunk_size. */
    public ?int $chunkSize = null;

    protected bool $confirmable = true;

    protected bool $recordsActionEvents = false;

    /** @var (Closure(Builder<Model>, ActionFields): (Builder<Model>|null))|null */
    protected ?Closure $queryCallback = null;

    /** @var (Closure(LatheRequest): list<mixed>)|null */
    protected ?Closure $fieldsCallback = null;

    /** @var (Closure(Model): array<string, mixed>)|null */
    protected ?Closure $formatCallback = null;

    protected Closure|string|null $filenameDefault = null;

    protected Closure|string|null $typeDefault = 'csv';

    public function name(): string
    {
        return $this->name ?? Lathe::__('Export');
    }

    public function uriKey(): string
    {
        return $this->uriKey ?? 'export';
    }

    /**
     * Filename and Type, then the fields withFields adds.
     *
     * @return list<mixed>
     */
    public function fields(LatheRequest $request): array
    {
        return [
            Field::text(Lathe::__('Filename'), 'filename')->required()->rules('max:255')->default($this->filenameDefault),
            Field::select(Lathe::__('Type'), 'type')->options(['csv' => 'CSV (.csv)', 'xlsx' => 'Excel (.xlsx)'])->default($this->typeDefault),
            ...($this->fieldsCallback === null ? [] : ($this->fieldsCallback)($request)),
        ];
    }

    /**
     * Where and how a queued export runs; an export above the inline size queues without it.
     */
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
     * @param  Closure(Builder<Model>, ActionFields): (Builder<Model>|null)  $callback  runs last on the query
     */
    public function withQuery(Closure $callback): static
    {
        $this->queryCallback = $callback;

        return $this;
    }

    /**
     * @param  Closure(LatheRequest): list<mixed>  $callback  extra modal fields
     */
    public function withFields(Closure $callback): static
    {
        $this->fieldsCallback = $callback;

        return $this;
    }

    /**
     * @param  Closure(Model): array<string, mixed>  $callback  the columns of one row, keyed by header
     */
    public function withFormat(Closure $callback): static
    {
        $this->formatCallback = $callback;

        return $this;
    }

    /**
     * The Filename the modal starts with.
     */
    public function nameable(Closure|string|null $default = null): static
    {
        $this->filenameDefault = $default;

        return $this;
    }

    /**
     * The Type the modal starts with, csv or xlsx.
     */
    public function withTypeSelector(Closure|string|null $default = null): static
    {
        $this->typeDefault = $default ?? 'csv';

        return $this;
    }

    /**
     * A row may be exported by whoever holds the export ability, which the run checks too;
     * runAction is not asked.
     */
    public function authorizedToRunOn(LatheRequest $request, Model $model): bool
    {
        $class = $request->resource();

        return $class !== null && app(ResourceAuthorization::class)->allows($request->user(), $class, 'export');
    }

    /**
     * Checks the export ability, then writes the file now or queues it.
     *
     * @return array{result: ActionResult, permitted: int, skipped: int, failed: int, errors: list<array{id: int|string|null, message: string}>, export_run?: array{id: int}}
     */
    public function handleRequest(LatheRequest $request, Selection $selection): array
    {
        $class = $request->resourceOrFail();
        $resource = $class::make();
        $authorization = app(ResourceAuthorization::class);

        abort_unless($authorization->allows($request->user(), $class, 'export'), 403, $authorization->denialMessage($class));

        $resolver = new SelectionResolver($resource, $request, $selection);
        $resolver->ensureNotEmpty();
        $fields = app(ActionFieldValidator::class)->validate($request, $this);
        $type = $this->type($request, $fields);
        $context = JobContext::capture($request);
        $export = Export::create([
            'user_id' => (string) $context->actor,
            'file_disk' => Lathe::config()->string('exports.disk'),
            'file_name' => $this->path($fields, $type),
        ]);

        if ($resolver->exceeds($resource->definition()->getBulkInlineMax())) {
            $job = new ProcessExport($export, $class, $this->uriKey(), $selection->toArray(), $fields->toArray(), $type, $context, $this->chunkSize());
            $job->tries = $this->tries;
            $job->timeout = $this->timeout;
            dispatch($job->onConnection($this->connection)->onQueue($this->queue));

            return ['result' => ActionResult::message(Lathe::__('Export started.')), 'permitted' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => [], 'export_run' => ['id' => $export->id]];
        }

        try {
            $this->write($export, $request, $resource, $selection, $fields, $type, $context->timezone, $this->chunkSize());
        } catch (Throwable $e) {
            $export->markFailed($e);

            throw $e;
        }

        return [
            'result' => ActionResult::download($export->downloadName(), $export->downloadUrl()),
            'permitted' => $export->total_rows,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];
    }

    /**
     * Writes the rows to a temp file, then streams it to the export's disk. processed_rows
     * moves every $chunk rows.
     *
     * @param  'csv'|'xlsx'  $type
     */
    public function write(Export $export, LatheRequest $request, Resource $resource, Selection $selection, ActionFields $fields, string $type, string $timezone, int $chunk): void
    {
        $query = (new SelectionResolver($resource, $request, $selection))->query();
        $query = $this->queryCallback === null ? $query : (($this->queryCallback)($query, $fields) ?? $query);
        $total = $this->countRows($query);
        $export->update(['total_rows' => $total]);

        if ($type === 'xlsx' && $total > self::XLSX_MAX_ROWS) {
            throw new RuntimeException('An XLSX file holds at most 1,048,575 rows.');
        }

        $columns = $this->formatCallback === null ? app(IndexPayload::class, ['request' => $request])->columns($resource) : null;
        $writer = new ExportWriter($type);
        $processed = 0;

        try {
            if ($columns !== null) {
                $writer->addRow(array_map(fn (Field $field) => $field->label(), $columns));
            }

            $model = $query->getModel();

            foreach ($query->lazyById($chunk, $model->getQualifiedKeyName(), $model->getKeyName()) as $row) {
                $values = $columns === null ? $this->formatted($row, $timezone) : $this->displayed($row, $columns, $timezone);

                if ($columns === null && $processed === 0) {
                    $writer->addRow(array_map(strval(...), array_keys($values)));
                }

                $writer->addRow(array_values($values));

                if (++$processed % $chunk === 0) {
                    $export->update(['processed_rows' => $processed]);
                }
            }

            $stream = fopen($writer->close(), 'rb') ?: throw new RuntimeException('The export temp file cannot be read.');
            Storage::disk($export->file_disk)->writeStream((string) $export->file_name, $stream);
            fclose($stream);
        } finally {
            $writer->delete();
        }

        $export->update(['processed_rows' => $processed, 'successful_rows' => $processed, 'completed_at' => now()]);
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function countRows(Builder $query): int
    {
        return $query->count();
    }

    protected function chunkSize(): int
    {
        return $this->chunkSize ?? Lathe::config()->integer('exports.chunk_size');
    }

    /**
     * The Type sent, else the default the modal shows.
     *
     * @return 'csv'|'xlsx'
     */
    protected function type(LatheRequest $request, ActionFields $fields): string
    {
        $type = $fields->get('type') ?? ($this->typeDefault instanceof Closure ? ($this->typeDefault)($request) : $this->typeDefault);

        return $type === 'xlsx' ? 'xlsx' : 'csv';
    }

    /**
     * {directory}/{ulid}-{slug}.{ext}, so two users exporting the same name never share a file.
     *
     * @param  'csv'|'xlsx'  $type
     */
    protected function path(ActionFields $fields, string $type): string
    {
        $name = $fields->get('filename');
        $slug = Str::slug(is_string($name) ? $name : '');

        return trim(Lathe::config()->string('exports.directory'), '/').'/'.Str::ulid().'-'.($slug === '' ? 'export' : $slug).".{$type}";
    }

    /**
     * The index columns of one row: a date in the actor's timezone, money as a decimal
     * string, anything else as the index shows it.
     *
     * @param  list<Field>  $columns
     * @return list<string>
     */
    protected function displayed(Model $model, array $columns, string $timezone): array
    {
        return array_map(function (Field $field) use ($model, $timezone) {
            $attribute = $field->attribute();

            if ($attribute !== '' && $this->isDate($model, $attribute)) {
                return $this->cell($model->getAttribute($attribute), $timezone);
            }

            $cell = $field->cell($model);

            return $field instanceof CurrencyField ? $this->cell($cell['value'], $timezone) : $cell['display'];
        }, $columns);
    }

    /**
     * @return array<string, string>
     */
    protected function formatted(Model $model, string $timezone): array
    {
        return array_map(fn (mixed $value) => $this->cell($value, $timezone), ($this->formatCallback ?? fn () => [])($model));
    }

    protected function cell(mixed $value, string $timezone): string
    {
        return match (true) {
            $value === null => '',
            $value instanceof DateTimeInterface => CarbonImmutable::instance($value)->setTimezone($timezone)->format('Y-m-d H:i:s'),
            is_bool($value) => $value ? 'Yes' : 'No',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value),
        };
    }

    protected function isDate(Model $model, string $attribute): bool
    {
        return in_array($attribute, $model->getDates(), true)
            || $model->hasCast($attribute, ['date', 'datetime', 'immutable_date', 'immutable_datetime', 'custom_datetime', 'immutable_custom_datetime', 'timestamp']);
    }
}
