<?php

namespace Lathe\Actions\Import;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One import run: the uploaded file, its column map, its mode and its counters. A dry run
 * and the real import run on the same row.
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
}
