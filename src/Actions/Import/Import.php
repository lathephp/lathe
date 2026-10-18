<?php

namespace Lathe\Actions\Import;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Lathe\Lathe;
use Lathe\Support\ConfigStore;

/**
 * One import run: the uploaded file, its column map, its mode and its counters. A dry run
 * and the real import run on the same row. Rows older than imports.prune_after_hours go
 * with their failed rows and files on `model:prune --model`.
 *
 * @property int $id
 * @property string $user_id
 * @property string $file_disk
 * @property string $file_name
 * @property string|null $original_name
 * @property class-string<Model> $model_class
 * @property array<int|string, string|null>|null $column_map
 * @property string $status
 * @property string $mode
 * @property int $total_rows
 * @property int $processed_rows
 * @property int $last_processed_row
 * @property int $created_rows
 * @property int $updated_rows
 * @property int $skipped_rows
 * @property int $failed_rows
 * @property string|null $error_file
 * @property string|null $exception
 * @property Carbon|null $completed_at
 */
class Import extends Model
{
    use Prunable;

    protected $table = 'lathe_imports';

    /** @var list<string> */
    protected $fillable = [
        'user_id', 'file_disk', 'file_name', 'original_name', 'model_class', 'column_map', 'status', 'mode',
        'total_rows', 'processed_rows', 'last_processed_row', 'created_rows', 'updated_rows', 'skipped_rows',
        'failed_rows', 'error_file', 'exception', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'column_map' => 'array',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'last_processed_row' => 'integer',
            'created_rows' => 'integer',
            'updated_rows' => 'integer',
            'skipped_rows' => 'integer',
            'failed_rows' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<FailedImportRow, $this>
     */
    public function failedRows(): HasMany
    {
        return $this->hasMany(FailedImportRow::class);
    }

    /**
     * Whether the user started this run.
     */
    public function ownedBy(int|string|null $userId): bool
    {
        return $userId !== null && (string) $this->user_id === (string) $userId;
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->subHours(app(ConfigStore::class)->integer('imports.prune_after_hours')));
    }

    protected function pruning(): void
    {
        $this->failedRows()->delete();
        Storage::disk($this->file_disk)->delete(array_values(array_filter([$this->file_name, $this->error_file])));
    }

    /**
     * The signed download link of the error CSV, valid as long as the import is kept.
     */
    public function errorsUrl(): ?string
    {
        if ($this->error_file === null) {
            return null;
        }

        return URL::temporarySignedRoute(
            'lathe.api.imports.downloadErrors',
            now()->addHours(app(ConfigStore::class)->integer('imports.prune_after_hours')),
            ['import' => $this->id],
        );
    }

    /**
     * The status the import modal, the progress toast and the run page poll.
     *
     * @return array<string, mixed>
     */
    public function toStatusArray(): array
    {
        $resource = Lathe::resources()->forModel($this->model_class);

        return [
            'id' => $this->id,
            'status' => $this->status,
            'mode' => $this->mode,
            'total_rows' => $this->total_rows,
            'processed_rows' => $this->processed_rows,
            'created_rows' => $this->created_rows,
            'updated_rows' => $this->updated_rows,
            'skipped_rows' => $this->skipped_rows,
            'failed_rows' => $this->failed_rows,
            'errors_url' => $this->errorsUrl(),
            'exception' => $this->exception,
            'first_errors' => $this->failedRows()->orderBy('row_number')->limit(20)->get(['row_number', 'errors'])
                ->map(fn (FailedImportRow $row) => ['row_number' => $row->row_number, 'errors' => $row->errors])
                ->all(),
            // The toast says "2,388 of 2,400 products imported"; null once the model has no resource.
            'resource_label' => $resource === null ? null : $resource::label(),
        ];
    }
}
