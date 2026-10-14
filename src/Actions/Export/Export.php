<?php

namespace Lathe\Actions\Export;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One export run: the file it writes, its progress and how it ended.
 *
 * @property int $id
 * @property string $user_id
 * @property string $file_disk
 * @property string|null $file_name
 * @property int $total_rows
 * @property int $processed_rows
 * @property int $successful_rows
 * @property Carbon|null $completed_at
 * @property Carbon|null $failed_at
 * @property string|null $exception
 */
class Export extends Model
{
    protected $table = 'lathe_exports';

    /** @var list<string> */
    protected $fillable = ['user_id', 'file_disk', 'file_name', 'total_rows', 'processed_rows', 'successful_rows', 'completed_at', 'failed_at', 'exception'];

    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'successful_rows' => 'integer',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * Whether the user started this run.
     */
    public function ownedBy(int|string|null $userId): bool
    {
        return $userId !== null && (string) $this->user_id === (string) $userId;
    }
}
