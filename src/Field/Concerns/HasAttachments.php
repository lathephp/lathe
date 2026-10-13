<?php

namespace Lathe\Field\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Support\ConfigStore;
use Lathe\Uploads\UploadHandler;

/**
 * Images uploaded into an editor while its record is written. Each upload carries a draft id
 * the server issued; the save moves the caller's uploads of that draft onto the record.
 */
trait HasAttachments
{
    protected bool $withFiles = false;

    protected ?string $attachmentDisk = null;

    /**
     * Let the editor upload images, stored on this disk (else config storage_disk).
     */
    public function withFiles(?string $disk = null): static
    {
        $this->mutate();
        $this->withFiles = true;
        $this->attachmentDisk = $disk;

        return $this->option('withFiles', true);
    }

    public function hasFiles(): bool
    {
        return $this->withFiles;
    }

    public function getAttachmentDisk(): string
    {
        return $this->attachmentDisk ?? app(ConfigStore::class)->string('storage_disk');
    }

    /**
     * Raster images only: the files sit on a disk that may serve them, where an SVG or HTML
     * file could run script.
     */
    public function acceptsAttachment(string $mime): bool
    {
        return in_array(strtolower($mime), ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true);
    }

    /**
     * Fill the value, then, once the record has a key, attach the caller's uploads of the
     * draft the form sent as `{attribute}DraftId`.
     */
    public function fill(LatheRequest $request, object $model, ?string $requestAttribute = null): ?Closure
    {
        $after = parent::fill($request, $model, $requestAttribute);
        $draftId = $request->input(($requestAttribute ?? $this->attribute).'DraftId');

        if (! $this->withFiles || ! is_string($draftId) || ! $model instanceof Model || ($model->exists && $this->isImmutable($request, $model))) {
            return $after;
        }

        return function () use ($after, $request, $draftId, $model): void {
            if ($after !== null) {
                $after();
            }

            app(UploadHandler::class)->persistDraft($request->user(), $draftId, $this->attribute, $model);
        };
    }
}
