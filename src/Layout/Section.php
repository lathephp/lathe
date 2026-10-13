<?php

namespace Lathe\Layout;

use Illuminate\Support\Str;
use Lathe\Field\Field;
use Lathe\Support\SafeHtml;
use Stringable;

/**
 * A titled group of fields, drawn as one panel.
 *
 * @phpstan-consistent-constructor
 */
class Section
{
    protected string $name;

    protected string $attribute;

    /** @var list<Field> */
    protected array $fields = [];

    protected bool $toolbar = false;

    protected bool $collapsible = false;

    protected bool $collapsed = false;

    protected bool $hideHeading = false;

    protected bool $headingInCard = false;

    protected ?string $help = null;

    protected ?int $limit = null;

    protected ?int $columns = null;

    protected ?int $columnSpan = null;

    protected bool $borderless = false;

    /**
     * @param  list<mixed>  $fields
     */
    public function __construct(Stringable|string $name, array $fields = [], ?string $attribute = null)
    {
        $this->name = (string) $name;
        $this->attribute = $attribute ?? Str::slug($this->name);
        $this->schema($fields);
    }

    /**
     * @param  list<mixed>  $fields
     */
    public static function make(Stringable|string $name, array $fields = [], ?string $attribute = null): static
    {
        return new static($name, $fields, $attribute);
    }

    /**
     * @param  list<mixed>  $fields  anything but a Field is ignored
     */
    public function schema(array $fields): static
    {
        $this->fields = array_values(array_filter($fields, fn (mixed $field) => $field instanceof Field));

        return $this;
    }

    public function withAttribute(string $attribute): static
    {
        $this->attribute = $attribute;

        return $this;
    }

    public function withToolbar(bool $toolbar = true): static
    {
        $this->toolbar = $toolbar;

        return $this;
    }

    public function collapsable(bool $collapsible = true): static
    {
        $this->collapsible = $collapsible;

        return $this;
    }

    public function collapsible(bool $collapsible = true): static
    {
        return $this->collapsable($collapsible);
    }

    public function collapsedByDefault(bool $collapsed = true): static
    {
        $this->collapsed = $collapsed;

        return $collapsed ? $this->collapsable() : $this;
    }

    public function collapsed(bool $collapsed = true): static
    {
        return $this->collapsedByDefault($collapsed);
    }

    public function hideHeading(bool $hide = true): static
    {
        $this->hideHeading = $hide;

        return $this;
    }

    public function headingInCard(bool $inCard = true): static
    {
        $this->headingInCard = $inCard;

        return $this;
    }

    /**
     * Help text as HTML. It goes through the sanitizer before it leaves the server.
     */
    public function help(string $html): static
    {
        $this->help = $html;

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function columns(?int $columns): static
    {
        $this->columns = SectionPresenter::columns($columns, "The section [{$this->name}] columns");

        return $this;
    }

    public function columnSpan(?int $span): static
    {
        $this->columnSpan = SectionPresenter::columns($span, "The section [{$this->name}] column span");

        return $this;
    }

    public function borderless(bool $borderless = true): static
    {
        $this->borderless = $borderless;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAttribute(): string
    {
        return $this->attribute;
    }

    /**
     * @return list<Field>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function getColumns(): ?int
    {
        return $this->columns;
    }

    public function getColumnSpan(): ?int
    {
        return $this->columnSpan;
    }

    /**
     * The panel block with the keys of the fields it shows.
     *
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    public function panel(array $fields): array
    {
        return [
            'type' => 'panel',
            'attribute' => $this->attribute,
            'name' => $this->name,
            'fields' => $fields,
            'toolbar' => $this->toolbar,
            'collapsible' => $this->collapsible,
            'collapsed' => $this->collapsed,
            'hideHeading' => $this->hideHeading,
            'headingInCard' => $this->headingInCard,
            'help' => $this->help === null ? null : SafeHtml::from($this->help),
            'limit' => $this->limit,
            'columns' => $this->columns,
            'columnSpan' => $this->columnSpan,
            'borderless' => $this->borderless,
        ];
    }
}
