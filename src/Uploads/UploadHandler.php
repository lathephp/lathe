<?php

namespace Lathe\Uploads;

use finfo;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lathe\Support\ConfigStore;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mime\MimeTypes;

/**
 * The chunked upload protocol. Chunks go to the local disk, never a public one, in a
 * folder per upload, and are assembled there on complete.
 */
class UploadHandler
{
    public function __construct(protected ConfigStore $config)
    {
        //
    }

    public function chunkSize(): int
    {
        return $this->config->integer('uploads.chunk_size');
    }

    /**
     * The key an upload's owner is stored under.
     */
    public function ownerId(?Authenticatable $user): string
    {
        $id = $user?->getAuthIdentifier();

        return is_scalar($id) ? (string) $id : '';
    }

    /**
     * The user's own upload. Another user's id answers 404, as if it did not exist.
     */
    public function owned(?Authenticatable $user, string $id): PendingUpload
    {
        return PendingUpload::findOwned($this->ownerId($user), $id) ?? throw new NotFoundHttpException;
    }

    /**
     * Open an upload. The disk and directory come from the caller's field definition.
     *
     * @param  array{name: string, size: int, mime: string, draft_id?: string|null}  $file
     */
    public function start(?Authenticatable $user, string $field, string $disk, string $directory, array $file): PendingUpload
    {
        if (! $this->extensionAllows($file['name'], $file['mime'])) {
            throw ValidationException::withMessages(['mime' => 'The file type does not match its extension.']);
        }

        return PendingUpload::create([
            'user_id' => $this->ownerId($user),
            'field' => $field,
            'disk' => $disk,
            'path' => $directory,
            'original_name' => $file['name'],
            'size' => $file['size'],
            'draft_id' => $file['draft_id'] ?? null,
            'status' => PendingUpload::PENDING,
        ]);
    }

    /**
     * Store one chunk. Sending an index again replaces it, so a retry stores once.
     */
    public function storeChunk(PendingUpload $upload, int $index, string $bytes): void
    {
        $length = strlen($bytes);
        $chunks = (int) ceil($upload->size / $this->chunkSize());

        if ($upload->status !== PendingUpload::PENDING || $length === 0 || $length > $this->chunkSize() || $index >= $chunks) {
            throw ValidationException::withMessages(['chunk' => 'The chunk is empty, too large or past the end of the file.']);
        }

        $this->disk()->put($upload->folder().'/'.$index, $bytes);
    }

    /**
     * The indexes of the chunks received so far, in order.
     *
     * @return list<int>
     */
    public function received(PendingUpload $upload): array
    {
        $indexes = [];

        foreach ($this->disk()->files($upload->folder()) as $file) {
            $name = basename($file);

            if (ctype_digit($name)) {
                $indexes[] = (int) $name;
            }
        }

        sort($indexes);

        return $indexes;
    }

    /**
     * Join the chunks into one file, then check its size and that its content is the type its
     * name claims. On a mismatch the chunks are removed and the client starts again.
     */
    public function complete(PendingUpload $upload): void
    {
        if ($upload->status !== PendingUpload::PENDING) {
            throw ValidationException::withMessages(['file' => 'The upload is already complete.']);
        }

        $assembled = $upload->folder().'/assembled';
        $this->assemble($upload, $assembled);

        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($this->disk()->path($assembled));

        if ($this->disk()->size($assembled) !== $upload->size || ! $this->extensionAllows($upload->original_name, $mime)) {
            $this->disk()->deleteDirectory($upload->folder());

            throw ValidationException::withMessages(['file' => 'The file is incomplete or its content does not match its type.']);
        }

        foreach ($this->received($upload) as $index) {
            $this->disk()->delete($upload->folder().'/'.$index);
        }

        $upload->update(['status' => PendingUpload::COMPLETE]);
    }

    public function cancel(PendingUpload $upload): void
    {
        $this->disk()->deleteDirectory($upload->folder());
        $upload->delete();
    }

    protected function assemble(PendingUpload $upload, string $path): void
    {
        $stream = fopen('php://temp', 'w+b');
        $chunks = (int) ceil($upload->size / $this->chunkSize());

        for ($index = 0; $index < $chunks && $stream !== false; $index++) {
            $chunk = $this->disk()->readStream($upload->folder().'/'.$index);

            if ($chunk !== null) {
                stream_copy_to_stream($chunk, $stream);
                fclose($chunk);
            }
        }

        if ($stream !== false) {
            rewind($stream);
            $this->disk()->writeStream($path, $stream);
            fclose($stream);
        }
    }

    /**
     * Whether the MIME type is one the name's extension stands for. The name only says what
     * the file claims to be; it is never used for a path.
     */
    protected function extensionAllows(string $name, string $mime): bool
    {
        $extension = Str::lower(pathinfo($name, PATHINFO_EXTENSION));

        return $extension !== '' && in_array(Str::lower($mime), MimeTypes::getDefault()->getMimeTypes($extension), true);
    }

    protected function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
