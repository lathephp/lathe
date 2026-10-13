<?php

namespace Lathe\Uploads;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * An image an editor field (Markdown or Tiptap withFiles()) holds, kept on the field's disk.
 * The record is named by its morph class and its key as a string, so any key type fits.
 *
 * @property int $id
 * @property string $attachable_type
 * @property string $attachable_id
 * @property string $attachment
 * @property string $disk
 * @property string $url
 */
class FieldAttachment extends Model
{
    protected $table = 'lathe_field_attachments';

    /**
     * Filled from a promoted upload only, never from request input.
     *
     * @var list<string>
     */
    protected $fillable = ['attachable_type', 'attachable_id', 'attachment', 'disk', 'url'];

    /**
     * The attachments of this record.
     *
     * @return Builder<self>
     */
    public static function of(Model $model): Builder
    {
        return static::where('attachable_type', $model->getMorphClass())->where('attachable_id', static::keyOf($model));
    }

    /**
     * The record's key as the string the attachable_id column holds.
     */
    public static function keyOf(Model $model): string
    {
        $key = $model->getKey();

        return is_scalar($key) ? (string) $key : '';
    }

    /**
     * Delete the file and the row.
     */
    public function purge(): void
    {
        Storage::disk($this->disk)->delete($this->attachment);
        $this->delete();
    }
}
