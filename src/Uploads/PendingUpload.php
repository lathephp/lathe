<?php

namespace Lathe\Uploads;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Lathe\Support\ConfigStore;

/**
 * A file on its way to a field: its chunks and, once complete, the assembled file sit on
 * the local disk until a save stores it on the field's disk. Rows older than
 * uploads.pending_hours go with their files on `model:prune --model`.
 *
 * @property string $id
 * @property string $user_id
 * @property string $field
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property int $size
 * @property string|null $draft_id
 * @property string $status
 * @property Carbon|null $created_at
 */
class PendingUpload extends Model
{
    use HasUlids, Prunable;

    public const string PENDING = 'pending';

    public const string COMPLETE = 'complete';

    /** The table has no updated_at column. */
    public const ?string UPDATED_AT = null;

    protected $table = 'lathe_pending_uploads';

    /**
     * The handler fills these from the field definition and validated input only.
     *
     * @var list<string>
     */
    protected $fillable = ['user_id', 'field', 'disk', 'path', 'original_name', 'size', 'draft_id', 'status'];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * The upload with this id, when it belongs to this user.
     */
    public static function findOwned(string $userId, string $id): ?self
    {
        return static::where('user_id', $userId)->find($id);
    }

    /**
     * The folder on the local disk that holds the chunks and the assembled file.
     */
    public function folder(): string
    {
        return 'lathe-uploads/'.$this->id;
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->subHours(app(ConfigStore::class)->integer('uploads.pending_hours')));
    }

    /**
     * An editor image already stored on its field's disk goes too, since no save will attach it.
     */
    protected function pruning(): void
    {
        Storage::disk('local')->deleteDirectory($this->folder());

        if ($this->status === UploadHandler::ATTACHED) {
            Storage::disk($this->disk)->delete($this->path);
        }
    }
}
