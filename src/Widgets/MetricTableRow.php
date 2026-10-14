<?php

namespace Lathe\Widgets;

use Closure;
use JsonSerializable;
use Lathe\Enums\Icon;
use Stringable;

/**
 * One row of a TableWidget, with an icon, a title, a subtitle and row links.
 */
class MetricTableRow implements JsonSerializable
{
    protected ?string $icon = null;

    protected ?string $iconClass = null;

    protected string $title = '';

    protected string $subtitle = '';

    /** @var (Closure(): array<int|string, mixed>)|null */
    protected ?Closure $actions = null;

    public static function make(): static
    {
        return new static;
    }

    public function icon(Icon $icon): static
    {
        $this->icon = $icon->value;

        return $this;
    }

    /**
     * Classes the client puts on the icon.
     */
    public function iconClass(string $class): static
    {
        $this->iconClass = $class;

        return $this;
    }

    public function title(string|Stringable $title): static
    {
        $this->title = (string) $title;

        return $this;
    }

    public function subtitle(string|Stringable $subtitle): static
    {
        $this->subtitle = (string) $subtitle;

        return $this;
    }

    /**
     * The row links, read when the row is sent.
     *
     * @param  Closure(): array<int|string, mixed>  $callback
     */
    public function actions(Closure $callback): static
    {
        $this->actions = $callback;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_filter([
            'title' => $this->title !== '' ? $this->title : null,
            'subtitle' => $this->subtitle !== '' ? $this->subtitle : null,
            'icon' => $this->icon,
            'iconClass' => $this->iconClass,
            'actions' => $this->actions === null ? null : ($this->actions)(),
        ], fn (mixed $value) => $value !== null);
    }
}
