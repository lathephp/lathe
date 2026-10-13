<?php

namespace Lathe\Field;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Lathe\Field\Contracts\Cover;
use Lathe\Support\ConfigStore;

/**
 * A raster image. SVG is left out of the defaults because an SVG can carry script, and the
 * server does no image processing: the browser downsizes a wide image before it uploads.
 */
class ImageField extends FileField implements Cover
{
    protected string $component = 'image-field';

    /** @var list<string> */
    protected array $acceptedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/bmp'];

    /**
     * @param  string|(Closure(Model): mixed)|null  $attribute  a Closure makes a computed field
     * @param  (Closure(Model): (string|int|float|bool|null))|null  $resolver
     */
    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);

        $this->options = [
            ...$this->options,
            'indexWidth' => 32,
            'detailWidth' => 128,
            'maxWidth' => null,
            'rounded' => false,
            'squared' => false,
            'aspect' => null,
        ];
    }

    /**
     * Shrink an image wider than this many pixels in the browser before it uploads.
     */
    public function maxWidth(int $pixels): static
    {
        return $this->option('maxWidth', $pixels);
    }

    public function detailWidth(int $pixels): static
    {
        return $this->option('detailWidth', $pixels);
    }

    public function rounded(): static
    {
        return $this->option('squared', false)->option('rounded', true);
    }

    public function squared(): static
    {
        return $this->option('rounded', false)->option('squared', true);
    }

    /**
     * The display ratio, such as "16/9".
     */
    public function aspect(string $ratio): static
    {
        return $this->option('aspect', $ratio);
    }

    public function isRounded(): bool
    {
        return $this->options['rounded'] === true;
    }

    public function isSquared(): bool
    {
        return $this->options['squared'] === true;
    }

    public function resolveThumbnailUrl(object $record): ?string
    {
        $file = $this->resolve($record);

        if (! is_array($file)) {
            return null;
        }

        $url = $file['thumbnail'] ?? $file['preview'];

        return is_string($url) ? $url : null;
    }

    public function resolveAvatar(object $record): Avatar
    {
        return new Avatar($this->resolveThumbnailUrl($record), null, [], $this->isRounded());
    }

    protected function defaultMaxSizeMb(): int
    {
        return app(ConfigStore::class)->integer('uploads.max_image_size_mb');
    }
}
