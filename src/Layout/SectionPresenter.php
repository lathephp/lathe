<?php

namespace Lathe\Layout;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lathe\Field\Evaluator;
use Lathe\Field\Field;
use Lathe\Field\FieldCollection;
use Lathe\Field\FieldContext;
use Lathe\Field\Relations\BelongsToField;
use Lathe\Field\Relations\HasOneField;
use Lathe\Field\Relations\MorphToField;
use Lathe\Field\Relations\RelationField;
use Lathe\Field\Relations\TagField;
use Lathe\Http\Requests\LatheRequest;
use LogicException;

/**
 * Turns the items of Resource::fields() into the frozen field list and into the layout tree.
 *
 * @phpstan-type Block array<string, mixed>
 */
class SectionPresenter
{
    /** @var array<string, array<string, string>> attribute => name, per kind of container */
    private array $seen = [];

    /** @var list<Field> */
    private array $fields = [];

    private int $groups = 0;

    /** @var array<int, true> */
    private array $visible = [];

    private ?LatheRequest $request = null;

    private FieldContext $context = FieldContext::Detail;

    private ?Model $model = null;

    /**
     * A column count or span: null, or 1 to 6.
     */
    public static function columns(?int $columns, string $what): ?int
    {
        if ($columns !== null && ($columns < 1 || $columns > 6)) {
            throw new InvalidArgumentException("{$what} is {$columns}; it takes 1 to 6.");
        }

        return $columns;
    }

    /**
     * Every field of the items in declaration order, each linked to its section by key. Fields
     * outside any container join the default section. Throws on a duplicate attribute and on
     * a span wider than its parent.
     *
     * @param  list<mixed>  $items
     * @return list<Field>
     */
    public static function flattenFields(array $items, ?string $defaultHeading = null): array
    {
        $presenter = new self;
        $presenter->collect($items, $defaultHeading === null ? null : Str::slug($defaultHeading), null);

        return $presenter->fields;
    }

    /**
     * The layout tree of one context: only the fields in $visible appear.
     *
     * @param  list<mixed>  $items
     * @return list<Block>
     */
    public static function layout(array $items, FieldCollection $visible, LatheRequest $request, FieldContext $context, ?Model $model = null, ?string $defaultHeading = 'Details'): array
    {
        $presenter = new self;
        foreach ($visible as $field) {
            $presenter->visible[spl_object_id($field)] = true;
        }

        $presenter->request = $request;
        $presenter->context = $context;
        $presenter->model = $model;

        $loose = $defaultHeading === null ? null : Section::make($defaultHeading, self::looseFields($items));
        $blocks = $presenter->blocks($items, $loose);

        if ($context->isForm()) {
            $blocks = self::filterLayoutForForm($blocks);
        }

        $placed = false;

        return self::withToolbar($blocks, $placed);
    }

    /**
     * Drop every relation block, and every panel, tab, tab group and grid that holds no field.
     *
     * @param  list<Block>  $layout
     * @return list<Block>
     */
    public static function filterLayoutForForm(array $layout): array
    {
        $kept = [];

        foreach ($layout as $block) {
            if (! in_array($block['type'], ['panel', 'grid', 'tab-group'], true)) {
                continue;
            }

            if ($block['type'] === 'panel') {
                if ($block['fields'] !== []) {
                    $kept[] = $block;
                }
            } elseif ($block['type'] === 'grid') {
                $block['items'] = self::filterLayoutForForm(self::blocksIn($block['items']));

                if ($block['items'] !== []) {
                    $kept[] = $block;
                }
            } else {
                $block['tabs'] = self::filterTabs(self::blocksIn($block['tabs']));

                if ($block['tabs'] !== []) {
                    $kept[] = $block;
                }
            }
        }

        return $kept;
    }

    /**
     * @param  list<Block>  $tabs
     * @return list<Block>
     */
    private static function filterTabs(array $tabs): array
    {
        $kept = [];

        foreach ($tabs as $tab) {
            $tab['items'] = self::filterLayoutForForm(self::blocksIn($tab['items']));

            if ($tab['items'] !== []) {
                $kept[] = $tab;
            }
        }

        return $kept;
    }

    /**
     * The first panel in the tree carries the page toolbar.
     *
     * @param  list<Block>  $blocks
     * @return list<Block>
     */
    private static function withToolbar(array $blocks, bool &$placed): array
    {
        $out = [];

        foreach ($blocks as $block) {
            if (! $placed && $block['type'] === 'panel') {
                $block['toolbar'] = $placed = true;
            } elseif (! $placed && $block['type'] === 'grid') {
                $block['items'] = self::withToolbar(self::blocksIn($block['items']), $placed);
            } elseif (! $placed) {
                $block['tabs'] = array_map(function (array $tab) use (&$placed) {
                    $tab['items'] = self::withToolbar(self::blocksIn($tab['items'] ?? []), $placed);

                    return $tab;
                }, self::blocksIn($block['tabs']));
            }

            $out[] = $block;
        }

        return $out;
    }

    /**
     * @return list<Block>
     */
    private static function blocksIn(mixed $value): array
    {
        /** @var list<Block> */
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }

    /**
     * @param  list<mixed>  $items
     * @return list<Field>
     */
    private static function looseFields(array $items): array
    {
        return array_values(array_filter($items, fn (mixed $item) => $item instanceof Field));
    }

    private static function groupAttribute(TabGroup $group, int $position): string
    {
        return $group->getAttribute() ?? 'tab-group-'.$position;
    }

    private static function implicitSection(Tab $tab): Section
    {
        return Section::make($tab->getLabel(), self::looseFields($tab->getItems()), $tab->getAttribute().'-fields')
            ->hideHeading()
            ->columns($tab->getColumns());
    }

    /**
     * @param  list<mixed>  $items
     */
    private function collect(array $items, ?string $looseSection, ?int $columns): void
    {
        foreach ($items as $item) {
            if ($item instanceof Field) {
                $this->collectField($item, $looseSection, false, $columns);
            } elseif ($item instanceof Section) {
                $this->register('section', $item->getAttribute(), $item->getName());
                $this->checkSpan($item->getColumnSpan(), $columns, "The section [{$item->getName()}]");

                foreach ($item->getFields() as $field) {
                    $this->collectField($field, $item->getAttribute(), true, $item->getColumns());
                }
            } elseif ($item instanceof Grid) {
                $this->collect($item->getItems(), null, $item->getColumns());
            } elseif ($item instanceof TabGroup) {
                $this->collectGroup($item, $columns);
            }
        }
    }

    private function collectGroup(TabGroup $group, ?int $columns): void
    {
        $attribute = self::groupAttribute($group, ++$this->groups);
        $this->register('tab group', $attribute, $group->getLabel() ?? $attribute);
        $this->checkSpan($group->getColumnSpan(), $columns, "The tab group [{$attribute}]");

        foreach ($group->getTabs() as $tab) {
            $this->register('tab', $tab->getAttribute(), $tab->getLabel());
            $this->collect($tab->getItems(), $tab->getAttribute().'-fields', $tab->getColumns());
        }
    }

    private function collectField(Field $field, ?string $section, bool $own, ?int $columns): void
    {
        $this->checkSpan($field->getColumnSpan(), $columns, "The field [{$field->label()}]");

        if ($section !== null && ($own || $field->getSection() === null) && $field->getSection() !== $section) {
            $field->withSection($section);
        }

        $this->fields[] = $field;
    }

    private function register(string $kind, string $attribute, string $name): void
    {
        if (isset($this->seen[$kind][$attribute])) {
            throw new LogicException("The {$kind} [{$name}] and the {$kind} [{$this->seen[$kind][$attribute]}] share the attribute [{$attribute}]; name one with withAttribute().");
        }

        $this->seen[$kind][$attribute] = $name;
    }

    private function checkSpan(?int $span, ?int $columns, string $what): void
    {
        if ($span !== null && $columns !== null && $span > $columns) {
            throw new InvalidArgumentException("{$what} spans {$span} columns, but its parent has {$columns}.");
        }
    }

    /**
     * @param  list<mixed>  $items
     * @return list<Block>
     */
    private function blocks(array $items, ?Section $loose): array
    {
        $blocks = [];

        if ($loose !== null && array_filter($loose->getFields(), fn (Field $field) => ! $this->lifted($field)) !== []) {
            $blocks[] = $this->panel($loose);
        }

        foreach ($items as $item) {
            if ($item instanceof Field) {
                if ($this->lifted($item) && isset($this->visible[spl_object_id($item)])) {
                    $blocks[] = $this->relationBlock($item);
                }
            } elseif ($item instanceof Section) {
                $blocks[] = $this->panel($item);
                array_push($blocks, ...$this->relationBlocks($item->getFields()));
            } elseif ($item instanceof Grid) {
                $blocks[] = ['type' => 'grid', 'columns' => $item->getColumns(), 'items' => $this->blocks($item->getItems(), null)];
            } elseif ($item instanceof TabGroup) {
                $blocks[] = $this->tabGroup($item);
            }
        }

        return $blocks;
    }

    /**
     * @return Block
     */
    private function panel(Section $section): array
    {
        return $section->panel(array_values(array_map(
            fn (Field $field) => $field->key(),
            array_filter($section->getFields(), fn (Field $field) => isset($this->visible[spl_object_id($field)]) && ! $this->lifted($field)),
        )));
    }

    /**
     * Whether a detail page draws the field as a block of its own: a relation panel, or a hasOne
     * or morphOne that is not ofMany.
     */
    private function lifted(Field $field): bool
    {
        if ($this->context !== FieldContext::Detail || ! $field instanceof RelationField) {
            return false;
        }

        if ($field instanceof HasOneField) {
            return ! $field->isOfMany();
        }

        return ! $field instanceof BelongsToField && ! $field instanceof MorphToField && ! $field instanceof TagField;
    }

    /**
     * The blocks of a section's lifted fields, which follow its panel.
     *
     * @param  list<Field>  $fields
     * @return list<Block>
     */
    private function relationBlocks(array $fields): array
    {
        return array_values(array_map(
            fn (Field $field) => $this->relationBlock($field),
            array_filter($fields, fn (Field $field) => $this->lifted($field) && isset($this->visible[spl_object_id($field)])),
        ));
    }

    /**
     * @return Block
     */
    private function relationBlock(Field $field): array
    {
        $type = $field instanceof HasOneField ? 'has-one-'.$field->attribute() : 'relation-field';

        return ['type' => $type, 'key' => $field->key(), 'field' => $field];
    }

    /**
     * @return Block
     */
    private function tabGroup(TabGroup $group): array
    {
        $position = ++$this->groups;
        $attribute = self::groupAttribute($group, $position);
        $tabs = [];

        foreach ($group->getTabs() as $tab) {
            if (! $this->canSee($tab)) {
                continue;
            }

            $tabs[] = [
                'attribute' => $tab->getAttribute(),
                'label' => $tab->getLabel(),
                'icon' => $tab->getIcon()?->value,
                'badge' => $this->badge($tab),
                'badgeType' => $tab->getBadgeType(),
                'columns' => $tab->getColumns(),
                'borderless' => $tab->isBorderless(),
                'items' => $this->blocks($tab->getItems(), self::implicitSection($tab)),
            ];
        }

        return [
            'type' => 'tab-group',
            'attribute' => $attribute,
            'label' => $group->getLabel(),
            'queryKey' => $group->getQueryKey() ?? ($position === 1 ? 'tab' : 'tab_'.$attribute),
            'activeTab' => $group->getActiveTab(),
            'vertical' => $group->isVertical(),
            'contained' => $group->isContained(),
            'columnSpan' => $group->getColumnSpan(),
            'tabs' => $tabs,
        ];
    }

    private function canSee(Tab $tab): bool
    {
        $callback = $tab->getCanSee();

        return $callback === null || (bool) Evaluator::for($this->request)->call(
            $callback,
            ['record' => $this->model, 'request' => $this->request],
            [$this->request, $this->model],
        );
    }

    private function badge(Tab $tab): ?string
    {
        $badge = $tab->getBadge();

        if (! $badge instanceof \Closure) {
            return $badge;
        }

        if ($this->context !== FieldContext::Detail) {
            return null;
        }

        $text = Evaluator::for($this->request)->call(
            $badge,
            ['record' => $this->model, 'request' => $this->request],
            [$this->model, $this->request],
        );

        return is_scalar($text) ? (string) $text : null;
    }
}
