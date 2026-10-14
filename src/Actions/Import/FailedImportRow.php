<?php

namespace Lathe\Actions\Import;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row of an import file that failed: its number in the file (the header is row 1), the
 * data and the messages by column.
 *
 * @property int $id
 * @property int $import_id
 * @property int $row_number
 * @property array<string, mixed> $data
 * @property array<string, list<string>> $errors
 */
class FailedImportRow extends Model
{
    protected $table = 'lathe_failed_import_rows';

    /** @var list<string> */
    protected $fillable = ['import_id', 'row_number', 'data', 'errors'];

    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'data' => 'array',
            'errors' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Import, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
}
