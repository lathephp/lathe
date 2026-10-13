<?php

namespace Lathe\Field\Concerns;

use Lathe\Field\AudioField;
use Lathe\Field\FileField;
use Lathe\Field\GravatarField;
use Lathe\Field\ImageField;
use Lathe\Field\UiAvatarField;

/**
 * The factories of the file, image, audio, avatar, gravatar and uiAvatar fields.
 */
trait UploadFactories
{
    public static function file(string $label, ?string $attribute = null): FileField
    {
        return (new FileField($label, $attribute))->hideFromIndex();
    }

    public static function image(string $label, ?string $attribute = null): ImageField
    {
        return new ImageField($label, $attribute);
    }

    public static function audio(string $label, ?string $attribute = null): AudioField
    {
        return new AudioField($label, $attribute);
    }

    /**
     * A round, centered image that offers no download.
     */
    public static function avatar(string $name = 'Avatar', ?string $attribute = null): ImageField
    {
        return (new ImageField($name, $attribute))->rounded()->textAlign('center')->disableDownload();
    }

    public static function gravatar(string $name = 'Avatar', string $attribute = 'email'): GravatarField
    {
        return (new GravatarField($name))->source($attribute);
    }

    public static function uiAvatar(string $name = 'Avatar', ?string $source = 'name'): UiAvatarField
    {
        return (new UiAvatarField($name))->source($source);
    }
}
