<?php

namespace Lathe\Actions\Export;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Lathe\Lathe;
use Throwable;

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
 * @property Carbon $created_at
 */
class Export extends Model
{
    use Prunable;

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

    /**
     * waiting until the job reads its first rows, then running, then finished or failed.
     *
     * @return 'waiting'|'running'|'finished'|'failed'
     */
    public function status(): string
    {
        return match (true) {
            $this->failed_at !== null => 'failed',
            $this->completed_at !== null => 'finished',
            $this->total_rows > 0 => 'running',
            default => 'waiting',
        };
    }

    /**
     * What the progress toast and the run page poll.
     *
     * @return array{id: int, status: string, total_rows: int, processed_rows: int, download_url: string|null, exception: string|null}
     */
    public function toStatusArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status(),
            'total_rows' => $this->total_rows,
            'processed_rows' => $this->processed_rows,
            'download_url' => $this->completed_at !== null ? $this->downloadUrl() : null,
            'exception' => $this->exception,
        ];
    }

    /**
     * A signed link to the file, valid as long as the file is kept.
     */
    public function downloadUrl(): string
    {
        return URL::temporarySignedRoute('lathe.api.exports.download', now()->addHours(Lathe::config()->integer('exports.prune_after_hours')), ['export' => $this->id]);
    }

    /**
     * The name the browser saves: the stored name without its unique prefix.
     */
    public function downloadName(): string
    {
        return Str::after(basename((string) $this->file_name), '-');
    }

    /**
     * Deletes what the run wrote and records why it stopped.
     */
    public function markFailed(Throwable $e): void
    {
        if ($this->file_name !== null) {
            Storage::disk($this->file_disk)->delete($this->file_name);
        }

        $this->update(['failed_at' => now(), 'exception' => $e->getMessage()]);
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->subHours(Lathe::config()->integer('exports.prune_after_hours')));
    }

    protected function pruning(): void
    {
        if ($this->file_name !== null) {
            Storage::disk($this->file_disk)->delete($this->file_name);
        }
    }
}
