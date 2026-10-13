<?php

namespace Lathe\Field;

use Illuminate\Support\Str;
use Lathe\Field\Contracts\Deletable;
use Lathe\Field\Contracts\Storable;
use Lathe\Support\ConfigStore;

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
        return ($this->maxSizeMb ?? app(ConfigStore::class)->integer('uploads.max_file_size_mb')) * 1024 * 1024;
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
        return [$this->attribute];
    }
}
