<?php

namespace Lathe\Field;

use Closure;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Lathe\Field\Contracts\Cover;
use Lathe\Lathe;

/**
 * Round initials drawn by the browser. The server sends the letters and the style and makes
 * no request, so no name leaves for a third party.
 */
class UiAvatarField extends Field implements Cover
{
    protected string $component = 'ui-avatar-field';

    protected float $fontSize = 0.5;

    protected string $avatarColor = '7F9CF5';

    protected string $backgroundColor = 'EBF4FF';

    protected bool $bold = false;

    protected ?string $source = 'name';

    /**
     * @param  string|(Closure(Model): mixed)|null  $attribute  unused: the value comes from source()
     * @param  (Closure(Model): (string|int|float|bool|null))|null  $resolver
     */
    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        // The Closure only marks the field computed, which keeps it off the forms; resolve() builds the value.
        parent::__construct($label, $attribute ?? fn () => null, $resolver);

        $this->options = [...$this->options, 'indexWidth' => 32, 'textAlign' => 'center'];
    }

    /**
     * The attribute the initials come from; null gives none.
     */
    public function source(?string $attribute): static
    {
        $this->mutate();
        $this->source = $attribute;

        return $this;
    }

    /**
     * The letter height as a share of the avatar size, from 0.1 to 1.
     */
    public function fontSize(float $size): static
    {
        $this->mutate();
        $this->fontSize = min(1.0, max(0.1, $size));

        return $this;
    }

    /**
     * The letter color as hex without the hash.
     */
    public function avatarColor(string $hex): static
    {
        $this->mutate();
        $this->avatarColor = $this->hex($hex);

        return $this;
    }

    public function backgroundColor(string $hex): static
    {
        $this->mutate();
        $this->backgroundColor = $this->hex($hex);

        return $this;
    }

    public function bold(bool $bold = true): static
    {
        $this->mutate();
        $this->bold = $bold;

        return $this;
    }

    /**
     * @return array{url: string|null, initials: string|null, style: array{fontSize: float, color: string, background: string, bold: bool}, rounded: bool}
     */
    public function resolve(object $record, ?string $attribute = null): array
    {
        return [
            'url' => $this->imageUrl($record),
            'initials' => $this->initials($record),
            'style' => ['fontSize' => $this->fontSize, 'color' => $this->avatarColor, 'background' => $this->backgroundColor, 'bold' => $this->bold],
            'rounded' => true,
        ];
    }

    public function resolveThumbnailUrl(object $record): ?string
    {
        return $this->imageUrl($record);
    }

    public function isRounded(): bool
    {
        return true;
    }

    public function isSquared(): bool
    {
        return false;
    }

    public function resolveAvatar(object $record): Avatar
    {
        $value = $this->resolve($record);

        return new Avatar($value['url'], $value['initials'], $value['style'], $value['rounded']);
    }

    protected function imageUrl(object $record): ?string
    {
        return null;
    }

    protected function initials(object $record): ?string
    {
        $text = $this->source === null ? null : data_get($record, $this->source);

        return is_string($text) ? Avatar::initialsOf($text) : null;
    }

    protected function hex(string $hex): string
    {
        $hex = ltrim($hex, '#');

        return preg_match('/^(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $hex) === 1
            ? strtoupper($hex)
            : throw new InvalidArgumentException(Lathe::__('A color is 3 or 6 hex digits, such as 7F9CF5.'));
    }
}
