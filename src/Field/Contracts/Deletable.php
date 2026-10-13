<?php

namespace Lathe\Field\Contracts;

/**
 * A field whose file staff may remove, and which the panel deletes from the disk with it.
 */
interface Deletable
{
    public function isDeletable(): bool;

    public function isPrunable(): bool;

    /**
     * The columns nulled when the file is removed.
     *
     * @return list<string>
     */
    public function columnsThatShouldBeDeleted(): array;
}
