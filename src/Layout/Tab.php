<?php

namespace Lathe\Layout;

use Closure;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lathe\Enums\Icon;
use Lathe\Field\Field;

/**
 * One page of a TabGroup. Loose fields inside it collect into an implicit panel.
 *
 * @phpstan-consistent-constructor
 */
class Tab
{
    /** The badge colours the client draws. */
    public const array BADGE_TYPES = ['info', 'success', 'warning', 'danger'];

    protected string $attribute;

    /** @var list<Field|Section|Grid|TabGroup> */
    protected array $items = [];

    protected ?Icon $icon = null;

    protected string|Closure|null $badge = null;

    protected ?string $badgeType = null;

    protected ?int $columns = null;

    protected bool $borderless = false;

    protected ?Closure $canSee = null;

    /**
     * @param  list<mixed>  $fields
     */
    public function __construct(protected string $label, array $fields = [])
    {
        $this->attribute = Str::slug($label);
        $this->schema($fields);
    }

    /**
     * @param  list<mixed>  $fields
     */
    public static function make(string $label, array $fields = []): static
    {
        return new static($label, $fields);
    }

    /**
     * A group whose tabs come from Tabs, string-keyed arrays (label => items), Sections and Fields.
     *
     * @param  array<array-key, mixed>  $tabs
     */
    public static function group(?string $label = null, array $tabs = []): TabGroup
    {
        return TabGroup::make($label)->tabs($tabs);
    }

    /**
     * @param  list<mixed>  $items  anything but a Field, Section, Grid or TabGroup is ignored
     */
    public function schema(array $items): static
    {
        $this->items = array_values(array_filter(
            $items,
            fn (mixed $item) => $item instanceof Field || $item instanceof Section || $item instanceof Grid || $item instanceof TabGroup,
        ));

        return $this;
    }

    public function withAttribute(string $attribute): static
    {
        $this->attribute = $attribute;

        return $this;
    }

    public function icon(Icon $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Text, or a Closure (?Model $record, LatheRequest $request) that runs on the detail page only.
     */
    public function badge(string|Closure|null $badge): static
    {
        $this->badge = $badge;

        return $this;
    }

    public function badgeType(string $type): static
    {
        if (! in_array($type, self::BADGE_TYPES, true)) {
            throw new InvalidArgumentException("The tab [{$this->label}] badge type [{$type}] is not one of ".implode(', ', self::BADGE_TYPES).'.');
        }

        $this->badgeType = $type;

        return $this;
    }

    public function columns(?int $columns): static
    {
        $this->columns = SectionPresenter::columns($columns, "The tab [{$this->label}] columns");

        return $this;
    }

    public function borderless(bool $borderless = true): static
    {
        $this->borderless = $borderless;

        return $this;
    }

    /**
     * Leave the tab out of the page when the closure (LatheRequest $request, ?Model $record) says no.
     */
    public function canSee(Closure $callback): static
    {
        $this->canSee = $callback;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getAttribute(): string
    {
        return $this->attribute;
    }

    /**
     * @return list<Field|Section|Grid|TabGroup>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getColumns(): ?int
    {
        return $this->columns;
    }

    public function getIcon(): ?Icon
    {
        return $this->icon;
    }

    public function getBadge(): string|Closure|null
    {
        return $this->badge;
    }

    public function getBadgeType(): ?string
    {
        return $this->badgeType;
    }

    public function isBorderless(): bool
    {
        return $this->borderless;
    }

    public function getCanSee(): ?Closure
    {
        return $this->canSee;
    }
}
