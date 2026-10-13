<?php

namespace Lathe\Field;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lathe\Field\Contracts\Deletable;
use Lathe\Field\Contracts\Storable;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Support\ConfigStore;
use Lathe\Uploads\UploadHandler;
use LogicException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A file the browser sends through the chunked upload protocol. The disk and directory
 * come from this definition, so a request can never choose where a file lands.
 */
class FileField extends Field implements Deletable, Storable
{
    protected string $component = 'file-field';

    protected ?string $disk = null;

    protected string $directory = '';

    protected ?int $maxSizeMb = null;

    /** @var list<string> */
    protected array $acceptedTypes = [];

    protected bool $deletable = true;

    protected bool $prunable = false;

    protected bool $downloadable = true;

    protected ?string $originalNameColumn = null;

    protected ?string $sizeColumn = null;

    protected ?Closure $storeAsCallback = null;

    protected ?Closure $storeCallback = null;

    protected ?Closure $deleteCallback = null;

    protected ?Closure $previewCallback = null;

    protected ?Closure $thumbnailCallback = null;

    protected ?Closure $downloadCallback = null;

    public function disk(string $disk): static
    {
        $this->mutate();
        $this->disk = $disk;

        return $this;
    }

    /**
     * The directory on the disk the file is stored in.
     */
    public function path(string $directory): static
    {
        $this->mutate();
        $this->directory = $directory;

        return $this;
    }

    public function maxSize(int $mb): static
    {
        $this->mutate();
        $this->maxSizeMb = $mb;

        return $this;
    }

    /**
     * Limit uploads to these MIME types ("image/png", "image/*") or extensions ("pdf", ".pdf").
     */
    public function acceptedTypes(string ...$mimesOrExtensions): static
    {
        $this->mutate();
        $this->acceptedTypes = array_values($mimesOrExtensions);

        return $this;
    }

    public function deletable(bool $deletable = true): static
    {
        $this->mutate();
        $this->deletable = $deletable;

        return $this;
    }

    public function prunable(bool $prunable = true): static
    {
        $this->mutate();
        $this->prunable = $prunable;

        return $this;
    }

    /**
     * Name the stored file: fn (UploadedFile $file): string, in place of 40 random characters.
     */
    public function storeAs(Closure $callback): static
    {
        $this->mutate();
        $this->storeAsCallback = $callback;

        return $this;
    }

    /**
     * Store the file yourself: fn ($request, $file, $model, $attribute, $disk, $path): string returns the stored path.
     */
    public function store(Closure $callback): static
    {
        $this->mutate();
        $this->storeCallback = $callback;

        return $this;
    }

    /**
     * Delete the file yourself: fn ($request, $model, $disk, $path).
     */
    public function delete(Closure $callback): static
    {
        $this->mutate();
        $this->deleteCallback = $callback;

        return $this;
    }

    /**
     * The preview URL: fn (string $path, string $disk): ?string, in place of the disk URL.
     */
    public function preview(Closure $callback): static
    {
        $this->mutate();
        $this->previewCallback = $callback;

        return $this;
    }

    /**
     * The thumbnail URL: fn (string $path, string $disk): ?string, in place of the disk URL.
     */
    public function thumbnail(Closure $callback): static
    {
        $this->mutate();
        $this->thumbnailCallback = $callback;

        return $this;
    }

    /**
     * Answer the download yourself: fn ($request, $model, $disk, $path) returns a response.
     */
    public function download(Closure $callback): static
    {
        $this->mutate();
        $this->downloadCallback = $callback;

        return $this;
    }

    public function disableDownload(): static
    {
        $this->mutate();
        $this->downloadable = false;

        return $this;
    }

    /**
     * Keep the uploaded file's name in this column; it is cleared with the file.
     */
    public function storeOriginalName(string $column): static
    {
        $this->mutate();
        $this->originalNameColumn = $column;

        return $this;
    }

    /**
     * Keep the file's size in bytes in this column; it is cleared with the file.
     */
    public function storeSize(string $column): static
    {
        $this->mutate();
        $this->sizeColumn = $column;

        return $this;
    }

    public function getStorageDisk(): string
    {
        return $this->disk ?? app(ConfigStore::class)->string('storage_disk');
    }

    public function getStorageDir(): string
    {
        return $this->directory;
    }

    /**
     * The definition holds no record, so it knows no stored path.
     */
    public function getStoragePath(): ?string
    {
        return null;
    }

    public function getMaxSizeBytes(): int
    {
        return ($this->maxSizeMb ?? $this->defaultMaxSizeMb()) * 1024 * 1024;
    }

    /**
     * The limit in megabytes when maxSize() is not called; a subclass names its own config key.
     */
    protected function defaultMaxSizeMb(): int
    {
        return app(ConfigStore::class)->integer('uploads.max_file_size_mb');
    }

    /**
     * @return list<string>
     */
    public function getAcceptedTypes(): array
    {
        return $this->acceptedTypes;
    }

    /**
     * Whether a file with this name and MIME type is one of the accepted types; any is when none are set.
     */
    public function accepts(string $name, string $mime): bool
    {
        if ($this->acceptedTypes === []) {
            return true;
        }

        $extension = Str::lower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = Str::lower($mime);

        foreach ($this->acceptedTypes as $type) {
            $type = Str::lower($type);

            $matches = str_contains($type, '/')
                ? Str::is($type, $mime)
                : $extension !== '' && ltrim($type, '.') === $extension;

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    public function isDeletable(): bool
    {
        return $this->deletable;
    }

    public function isPrunable(): bool
    {
        return $this->prunable;
    }

    public function columnsThatShouldBeDeleted(): array
    {
        return array_values(array_filter(
            [$this->attribute, $this->originalNameColumn, $this->sizeColumn],
            fn (?string $column) => $column !== null,
        ));
    }

    /**
     * Attach an upload (the request value is its upload_id) or remove the file (`{attribute}_removed`).
     * A new file stores before the save; the old one goes in the returned Closure, after the save,
     * and only when the field is prunable. A request with neither keeps the stored file.
     */
    public function fill(LatheRequest $request, object $model, ?string $requestAttribute = null): ?Closure
    {
        $requestAttribute ??= $this->attribute;

        if (! $model instanceof Model || $this->fillCallback !== null || ($model->exists && $this->isImmutable($request, $model))) {
            return parent::fill($request, $model, $requestAttribute);
        }

        $old = $this->storedPath($model);
        $uploadId = $request->input($requestAttribute);

        if (is_string($uploadId) && $uploadId !== '') {
            $uploads = app(UploadHandler::class);

            // Only the user who uploaded the file may attach it, and only to the field it was started for.
            $upload = $uploads->attachable($request->user(), $uploadId, $this->attribute)
                ?? throw ValidationException::withMessages([$requestAttribute => Lathe::__('The upload is missing or not finished.')]);

            $path = $this->storeFile($request, $model, $uploads->file($upload));

            $model->setAttribute($this->attribute, $path);
            $this->fillColumn($model, $this->originalNameColumn, $upload->original_name);
            $this->fillColumn($model, $this->sizeColumn, $upload->size);

            // A failed save never reaches this, so the pending upload stays for a retry.
            // Files cannot roll back, so they go only once the write has committed.
            return fn () => $this->afterCommit($model, function () use ($request, $model, $uploads, $upload, $old, $path) {
                $uploads->cancel($upload);

                if ($old !== null && $old !== $path && $this->prunable) {
                    $this->deleteFile($request, $model, $old);
                }
            });
        }

        if (! $request->boolean($requestAttribute.'_removed') || ! $this->deletable) {
            return null;
        }

        foreach ($this->columnsThatShouldBeDeleted() as $column) {
            $model->setAttribute($column, null);
        }

        return $old !== null && $this->prunable
            ? fn () => $this->afterCommit($model, fn () => $this->deleteFile($request, $model, $old))
            : null;
    }

    /**
     * The file as the client shows it. Only URLs are built, so a list never touches the disk.
     */
    public function resolve(object $record, ?string $attribute = null): mixed
    {
        $path = parent::resolve($record, $attribute);

        if (! is_string($path) || $path === '') {
            return null;
        }

        $name = $this->originalNameColumn === null ? null : data_get($record, $this->originalNameColumn);

        return [
            'path' => $path,
            'name' => is_string($name) && $name !== '' ? $name : basename($path),
            'size' => $this->sizeColumn === null ? null : data_get($record, $this->sizeColumn),
            'preview' => $this->fileUrl($this->previewCallback, $path),
            'thumbnail' => $this->fileUrl($this->thumbnailCallback, $path),
            'downloadable' => $this->downloadable,
        ];
    }

    /**
     * The file name as the display text, and the download address when the file can be downloaded.
     */
    public function cell(Model $model): array
    {
        $cell = parent::cell($model);
        $name = is_array($cell['value']) ? ($cell['value']['name'] ?? null) : null;

        if (! is_string($name)) {
            return $cell;
        }

        $cell = ['value' => $cell['value'], 'display' => $name];
        $download = $this->downloadUrl($model);

        return $download === null ? $cell : [...$cell, 'download' => $download];
    }

    /**
     * The limit in megabytes and the accepted types travel too, so the browser refuses a file
     * before it sends a byte.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            ...parent::jsonSerialize(),
            'deletable' => $this->deletable,
            'maxSize' => $this->maxSizeMb ?? $this->defaultMaxSizeMb(),
            'acceptedTypes' => $this->acceptedTypes,
        ];
    }

    /**
     * The path stored on the record, if any.
     */
    public function storedPath(Model $model): ?string
    {
        $path = $model->getAttribute($this->attribute);

        return is_string($path) && $path !== '' ? $path : null;
    }

    /**
     * Delete a file of this field from its disk, the stored one when no path is given.
     */
    /**
     * Run after the model's transaction commits; at once when none is open.
     */
    protected function afterCommit(object $model, Closure $callback): void
    {
        ($model instanceof Model ? $model->getConnection() : DB::connection())->afterCommit($callback);
    }

    public function deleteFile(LatheRequest $request, Model $model, ?string $path = null): void
    {
        $path ??= $this->storedPath($model);

        if ($path === null) {
            return;
        }

        if ($this->deleteCallback !== null) {
            $this->evaluate($this->deleteCallback, ['request' => $request, 'record' => $model], [$request, $model, $this->getStorageDisk(), $path]);

            return;
        }

        $this->storage()->delete($path);
    }

    /**
     * The stored file as an attachment, named by the original-name column else its basename.
     * 404 when downloads are off or the file is missing.
     */
    public function downloadResponse(LatheRequest $request, Model $model): Response
    {
        $path = $this->storedPath($model);

        if (! $this->downloadable || $path === null) {
            throw new NotFoundHttpException;
        }

        if ($this->downloadCallback !== null) {
            $response = $this->evaluate($this->downloadCallback, ['request' => $request, 'record' => $model], [$request, $model, $this->getStorageDisk(), $path]);

            return $response instanceof Response ? $response : throw new LogicException("The download Closure of the field [{$this->attribute}] must return a response.");
        }

        if (! $this->storage()->exists($path)) {
            throw new NotFoundHttpException;
        }

        $name = $this->originalNameColumn === null ? null : $model->getAttribute($this->originalNameColumn);

        return $this->storage()->download($path, is_string($name) && $name !== '' ? $name : basename($path), [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    protected function storeFile(LatheRequest $request, Model $model, UploadedFile $file): string
    {
        $disk = $this->getStorageDisk();

        $path = match (true) {
            $this->storeCallback !== null => $this->evaluate(
                $this->storeCallback,
                ['request' => $request, 'record' => $model],
                [$request, $file, $model, $this->attribute, $disk, $this->directory],
            ),
            $this->storeAsCallback !== null => $file->storeAs(
                $this->directory,
                $this->stringOf($this->evaluate($this->storeAsCallback, ['request' => $request, 'record' => $model], [$file])),
                ['disk' => $disk],
            ),
            default => $file->store($this->directory, ['disk' => $disk]),
        };

        return is_string($path) && $path !== '' ? $path : throw new LogicException("The file of the field [{$this->attribute}] was not stored.");
    }

    protected function fillColumn(Model $model, ?string $column, mixed $value): void
    {
        if ($column !== null) {
            $model->setAttribute($column, $value);
        }
    }

    /**
     * The download route of this record, known only inside a Resource request.
     */
    protected function downloadUrl(Model $model): ?string
    {
        $resource = Evaluator::currentRequest()?->route('resource');

        if (! $this->downloadable || ! is_string($resource)) {
            return null;
        }

        return route('lathe.api.resources.field.download', ['resource' => $resource, 'resourceId' => $model->getKey(), 'field' => $this->attribute], false);
    }

    protected function fileUrl(?Closure $callback, string $path): ?string
    {
        if ($callback === null) {
            return $this->storage()->url($path);
        }

        $url = $this->evaluate($callback, [], [$path, $this->getStorageDisk()]);

        return is_string($url) ? $url : null;
    }

    protected function stringOf(mixed $value): string
    {
        return is_string($value) ? $value : throw new LogicException("The storeAs Closure of the field [{$this->attribute}] must return a string.");
    }

    protected function storage(): FilesystemAdapter
    {
        return Storage::disk($this->getStorageDisk());
    }
}
