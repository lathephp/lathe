<?php

namespace Lathe\Field\Contracts;

/**
 * A field that keeps a file on a disk. The disk and directory come from the field, never from a request.
 */
interface Storable
{
    public function getStorageDisk(): string;

    public function getStorageDir(): string;

    public function getStoragePath(): ?string;
}
