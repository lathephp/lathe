<?php

namespace Lathe\Field\Contracts;

use Lathe\Field\Avatar;

/**
 * A field that draws a picture for its record. The first one of a Resource's detail fields is the Resource avatar.
 */
interface Cover
{
    /**
     * The picture's URL, built without touching the disk; null when the record has none.
     */
    public function resolveThumbnailUrl(object $record): ?string;

    public function isRounded(): bool;

    public function isSquared(): bool;

    public function resolveAvatar(object $record): Avatar;
}
