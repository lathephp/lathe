<?php

namespace Lathe\Field\Concerns;

use Lathe\Field\FileField;

/**
 * The factories of the file, image, audio, avatar, gravatar and uiAvatar fields.
 */
trait UploadFactories
{
    public static function file(string $label, ?string $attribute = null): FileField
    {
        return new FileField($label, $attribute);
    }
}
