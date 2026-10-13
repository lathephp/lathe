<?php

namespace Lathe\Field;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Lathe\Field\Repeater\HasManyPreset;
use Lathe\Field\Repeater\Repeatable;
use Lathe\Field\Repeater\RowsLifecycle;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Support\ConfigStore;
use stdClass;

/**
 * A list of rows edited in place. Each row is drawn by ordinary fields under scoped keys
 * (`specifications.0.name`); the storage preset keeps the rows, a JSON column by default.
 */
class RepeaterField extends Field
{
    protected string $component = 'repeater-field';

    /** @var 'fields'|'simple'|'repeatables' */
    protected string $mode = 'fields';

    /** @var list<Field> */
    protected array $subFields = [];

    /** @var list<Repeatable> */
    protected array $blocks = [];

    protected ?string $distinct = null;

    protected int $defaultRows = 0;

    protected ?Closure $labelCallback = null;

    protected ?Closure $beforeFill = null;

    protected ?Closure $beforeSave = null;

    /**
     * Rows carry their child's key as `_key` (the hasMany preset).
     *
     * @internal
     */
    protected bool $keyed = false;

    /**
     * The record a fill() writes to, while it runs: file sub-fields store under it.
     *
     * @internal
     */
    protected ?Model $fillRecord = null;

    /**
     * The stored rows, while a fill() runs: a file sub-field keeps only a path found here.
     *
     * @internal
     *
     * @var list<mixed>
     */
    protected array $storedRows = [];

    /**
     * What the sub-fields deferred until after save, while a fill() runs.
     *
     * @internal
     *
     * @var list<Closure>
     */
    protected array $rowCallbacks = [];

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);

        $this->visibility['index'] = false;
        $this->lifecycle = new RowsLifecycle($this, new DefaultLifecycle);
        $this->options += [
            'reorderable' => true,
            'addable' => true,
            'deletable' => true,
            'collapsible' => false,
            'collapsed' => false,
            'cloneable' => false,
            'itemNumbers' => false,
            'confirmRemoval' => false,
            'grid' => null,
            'addButtonText' => null,
            'rowLabel' => null,
            'min' => null,
            'max' => null,
        ];
    }

    // Modes

    /**
     * Every row holds these fields.
     *
     * @param  list<Field>  $fields
     */
    public function fields(array $fields): static
    {
        return $this->mode('fields', $fields);
    }

    /**
     * Each row is the one value of this field, stored as a flat array such as ["a", "b"].
     */
    public function simple(Field $field): static
    {
        return $this->mode('simple', [$field]);
    }

    /**
     * Each row is one of these blocks: it holds `type`, the block key, and that block's fields.
     *
     * @param  list<Repeatable>  $blocks
     */
    public function repeatables(array $blocks): static
    {
        $this->mode('repeatables', []);
        $this->blocks = $blocks;

        return $this;
    }

    /**
     * @param  'fields'|'simple'|'repeatables'  $mode
     * @param  list<Field>  $fields
     */
    protected function mode(string $mode, array $fields): static
    {
        $this->mutate();
        $this->mode = $mode;
        $this->subFields = $fields;
        $this->blocks = [];

        return $this;
    }

    /**
     * Keep the rows as an array in the attribute (array and json casts). The default.
     */
    public function asJson(): static
    {
        $this->keyed = false;

        return $this->useLifecycle(new RowsLifecycle($this, new DefaultLifecycle));
    }

    /**
     * Keep each row as a child of this hasMany relation. A row matches its child by `_key`, else
     * by the uniqueField value; the row index goes to the orderColumn. A beforeSave() that
     * rebuilds the rows must keep `_key`, or every child is created anew.
     */
    public function asHasMany(string $relationship, ?string $uniqueField = null, ?string $orderColumn = null): static
    {
        $this->useLifecycle(new RowsLifecycle($this, new HasManyPreset($relationship, $uniqueField, $orderColumn)));
        $this->keyed = true;

        return $this;
    }

    // Options

    /**
     * Let staff drag rows into a new order. On by default.
     */
    public function sortable(bool $sortable = true): static
    {
        return $this->option('reorderable', $sortable);
    }

    public function min(int $rows): static
    {
        return $this->option('min', $rows);
    }

    /**
     * The most rows; a submission with more is refused before any rule runs.
     */
    public function max(int $rows): static
    {
        return $this->option('max', $rows);
    }

    public function collapsible(bool $collapsible = true): static
    {
        return $this->option('collapsible', $collapsible);
    }

    /**
     * Start every row collapsed; it makes the rows collapsible.
     */
    public function collapsed(bool $collapsed = true): static
    {
        $this->option('collapsed', $collapsed);

        return $collapsed ? $this->collapsible() : $this;
    }

    public function cloneable(bool $cloneable = true): static
    {
        return $this->option('cloneable', $cloneable);
    }

    public function itemNumbers(bool $show = true): static
    {
        return $this->option('itemNumbers', $show);
    }

    public function addable(bool $addable = true): static
    {
        return $this->option('addable', $addable);
    }

    public function deletable(bool $deletable = true): static
    {
        return $this->option('deletable', $deletable);
    }

    public function confirmRemoval(bool $confirm = true): static
    {
        return $this->option('confirmRemoval', $confirm);
    }

    /**
     * Rows the create form starts with.
     */
    public function defaultRows(int $rows): static
    {
        $this->mutate();
        $this->defaultRows = max(0, $rows);

        return $this;
    }

    /**
     * Lay a row out in 1 to 6 columns.
     */
    public function grid(int $columns): static
    {
        if ($columns < 1 || $columns > 6) {
            throw new InvalidArgumentException("The repeater [{$this->label}] has a grid of {$columns} columns; a grid takes 1 to 6.");
        }

        return $this->option('grid', $columns);
    }

    /**
     * The row heading: the live value of a sub-field, else the fallback template
     * (`{{index}}` is the row number), else "Row #N". A Closure (array $row, int $index)
     * runs on the server as the rows load.
     */
    public function rowLabel(Closure|string $label, ?string $fallback = null): static
    {
        $this->mutate();
        $this->labelCallback = $label instanceof Closure ? $label : null;

        return $this->option('rowLabel', ['field' => is_string($label) ? $label : null, 'fallback' => $fallback]);
    }

    public function addButtonText(string $text): static
    {
        return $this->option('addButtonText', $text);
    }

    /**
     * Refuse two rows with the same value of this sub-field.
     */
    public function distinct(string $subFieldAttribute): static
    {
        $this->mutate();
        $this->distinct = $subFieldAttribute;

        return $this;
    }

    /**
     * Shape the rows once after they load: fn (array $rows): array.
     */
    public function beforeFill(Closure $callback): static
    {
        $this->mutate();
        $this->beforeFill = $callback;

        return $this;
    }

    /**
     * Shape the rows once before they are stored: fn (array $rows): array.
     */
    public function beforeSave(Closure $callback): static
    {
        $this->mutate();
        $this->beforeSave = $callback;

        return $this;
    }

    // Values

    /**
     * The wire rows of the stored rows: each sub-field resolves against its own row.
     *
     * @internal
     *
     * @param  list<mixed>  $rows
     * @return list<mixed>
     */
    public function presentRows(array $rows): array
    {
        $presented = [];

        foreach ($rows as $row) {
            if ($this->mode === 'simple') {
                $presented[] = $this->subFields[0]->resolve((object) [$this->subFields[0]->attribute() => $row]);
            } elseif (is_array($row) && ($fields = $this->rowFields($row)) !== null) {
                $values = [];

                foreach ($fields as $field) {
                    $values[$field->attribute()] = $field->resolve((object) $row);
                }

                if ($this->keyed && isset($row['_key'])) {
                    $values['_key'] = $row['_key'];
                }

                $presented[] = $this->mode === 'repeatables' ? ['type' => $row['type'], ...$values] : $values;
            }
        }

        $presented = $this->shape($this->beforeFill, $presented);
        $callback = $this->labelCallback;

        if ($callback === null) {
            return $presented;
        }

        return array_map(function (mixed $row, int $index) use ($callback) {
            if (! is_array($row)) {
                return $row;
            }

            $label = $this->evaluate($callback, [], [$row, $index]);

            return [...$row, '_label' => is_scalar($label) ? (string) $label : ''];
        }, $presented, array_keys($presented));
    }

    /**
     * The rows of the request: each sub-field fills a fresh row from its scoped key.
     *
     * @internal
     *
     * @return list<mixed>
     */
    public function parseRows(LatheRequest $request, string $attribute): array
    {
        $input = $request->input($attribute);
        $rows = [];

        foreach (is_array($input) ? $input : [] as $index => $row) {
            if ($this->mode === 'simple') {
                $target = new stdClass;
                $this->subFields[0]->fill($request, $target, "{$attribute}.{$index}");
                $rows[] = data_get($target, $this->subFields[0]->attribute());
            } elseif (is_array($row) && ($fields = $this->rowFields($row)) !== null) {
                $target = new stdClass;

                foreach ($fields as $field) {
                    if (! $field->isComputed()) {
                        $this->fillCell($field, $request, $target, "{$attribute}.{$index}.{$field->attribute()}");
                    }
                }

                if ($this->keyed && is_scalar($row['_key'] ?? null)) {
                    $target->_key = $row['_key'];
                }

                $rows[] = $this->mode === 'repeatables' ? ['type' => $row['type'], ...(array) $target] : (array) $target;
            }
        }

        return $this->shape($this->beforeSave, $rows);
    }

    /**
     * Fill one sub-field of a row and keep what it defers until after save. A file sub-field
     * attaches its upload to the row, keeps a path only when the stored rows hold it, and
     * otherwise clears its columns, so a client never names a file of its own choosing.
     *
     * @internal
     */
    protected function fillCell(Field $field, LatheRequest $request, object $target, string $path): void
    {
        if (! $field instanceof FileField || $this->fillRecord === null || $field->fillCallback !== null) {
            $after = $field->fill($request, $target, $path);
        } else {
            $value = $request->input($path);
            $after = is_string($value) && $value !== '' ? $field->attachToRow($request, $this->fillRecord, $target, $path, $value) : null;
            $stored = $after === null ? $this->storedRow($field, $value) : null;

            foreach ($after === null ? $field->columnsThatShouldBeDeleted() : [] as $column) {
                data_set($target, $column, $stored === null ? null : data_get($stored, $column));
            }
        }

        if ($after !== null) {
            $this->rowCallbacks[] = $after;
        }
    }

    /**
     * The stored row whose file of this sub-field is the path the request names, if any.
     *
     * @internal
     *
     * @return array<mixed>|null
     */
    protected function storedRow(FileField $field, mixed $value): ?array
    {
        $path = is_array($value) ? ($value['path'] ?? null) : null;

        foreach (is_string($path) && $path !== '' ? $this->storedRows : [] as $row) {
            if (is_array($row) && ($row[$field->attribute()] ?? null) === $path) {
                return $row;
            }
        }

        return null;
    }

    /**
     * The sub-field at a dotted path below this repeater, such as `0.photo` or `2.children.0.photo`.
     *
     * @internal
     */
    public function findSubField(string $path): ?Field
    {
        $segments = explode('.', $path, 3);

        if (count($segments) < 2 || ! ctype_digit($segments[0]) || $this->mode === 'simple') {
            return null;
        }

        $fields = $this->mode === 'repeatables'
            ? array_merge(...array_map(fn (Repeatable $block) => $block->getFields(), $this->blocks))
            : $this->subFields;

        foreach ($fields as $field) {
            if ($field->attribute() === $segments[1]) {
                return match (true) {
                    ! isset($segments[2]) => $field,
                    $field instanceof self => $field->findSubField($segments[2]),
                    default => null,
                };
            }
        }

        return null;
    }

    /**
     * The fields of one row: the repeater's, or those of the row's block; null for an unknown block.
     *
     * @param  array<mixed>  $row
     * @return list<Field>|null
     */
    protected function rowFields(array $row): ?array
    {
        return $this->mode === 'repeatables' ? $this->block($row['type'] ?? null)?->getFields() : $this->subFields;
    }

    protected function block(mixed $type): ?Repeatable
    {
        foreach ($this->blocks as $block) {
            if ($block->key() === $type) {
                return $block;
            }
        }

        return null;
    }

    /**
     * @param  list<mixed>  $rows
     * @return list<mixed>
     */
    protected function shape(?Closure $callback, array $rows): array
    {
        if ($callback === null) {
            return $rows;
        }

        $shaped = $this->evaluate($callback, ['value' => $rows], [$rows]);

        return is_array($shaped) ? array_values($shaped) : [];
    }

    /**
     * Without a default(), defaultRows() empty rows.
     */
    public function resolveDefaultValue(?LatheRequest $request = null): mixed
    {
        if ($this->default !== null || $this->defaultRows === 0) {
            return parent::resolveDefaultValue($request);
        }

        $first = $this->blocks[0] ?? null;

        $row = match ($this->mode) {
            'simple' => $this->subFields[0]->resolveDefaultValue($request),
            'fields' => $this->emptyRow($this->subFields, $request),
            'repeatables' => $first === null ? null : ['type' => $first->key(), ...$this->emptyRow($first->getFields(), $request)],
        };

        return $row === null && $this->mode === 'repeatables' ? [] : array_fill(0, $this->defaultRows, $row);
    }

    /**
     * @param  list<Field>  $fields
     * @return array<string, mixed>
     */
    protected function emptyRow(array $fields, ?LatheRequest $request): array
    {
        $row = [];

        foreach ($fields as $field) {
            $row[$field->attribute()] = $field->resolveDefaultValue($request);
        }

        return $row;
    }

    /**
     * Fill the rows, then return one Closure that runs what the sub-fields deferred and what
     * the storage deferred, in that order, after the save.
     */
    public function fill(LatheRequest $request, object $model, ?string $requestAttribute = null): ?Closure
    {
        $this->fillRecord = $model instanceof Model ? $model : null;
        $this->storedRows = $this->fillRecord?->exists === true ? $this->storedRowsOf($this->fillRecord) : [];
        $this->rowCallbacks = [];

        try {
            $stored = parent::fill($request, $model, $requestAttribute);
            $callbacks = $stored === null ? $this->rowCallbacks : [...$this->rowCallbacks, $stored];
        } finally {
            $this->fillRecord = null;
            $this->storedRows = [];
            $this->rowCallbacks = [];
        }

        return $callbacks === [] ? null : function () use ($callbacks) {
            foreach ($callbacks as $callback) {
                $callback();
            }
        };
    }

    /**
     * The stored rows a file sub-field may keep a path from; none without a file sub-field.
     *
     * @internal
     *
     * @return list<mixed>
     */
    protected function storedRowsOf(Model $model): array
    {
        $fields = array_merge($this->subFields, ...array_map(fn (Repeatable $block) => $block->getFields(), $this->blocks));
        $raw = array_filter($fields, fn (Field $field) => $field instanceof FileField) === [] ? null : $this->lifecycle->extract($model, $this->attribute);

        return is_array($raw) ? array_values($raw) : [];
    }

    // Validation

    public function getRules(FieldContext $context, ?LatheRequest $request = null, ?Model $model = null): array
    {
        return $this->rulesAt($this->attribute, $context, $request, $model);
    }

    /**
     * The rules of this repeater under a path: its own key, then each row's sub-fields under
     * `path.N.sub`. More rows than the cap leaves only the cap, so no other rule runs.
     *
     * @internal
     *
     * @return array<string, list<mixed>>
     */
    public function rulesAt(string $path, FieldContext $context, ?LatheRequest $request = null, ?Model $model = null): array
    {
        $input = $request?->input($path);
        $rows = is_array($input) ? $input : [];
        $cap = $this->maxRows();

        if (count($rows) > $cap) {
            return [$path => ['array', "max:{$cap}"]];
        }

        $rules = [$path => parent::getRules($context, $request, $model)[$this->attribute]];

        if ($this->mode === 'simple') {
            $field = $this->subFields[0];

            foreach ($field->getRules($context, $request) as $key => $fieldRules) {
                $rules["{$path}.*".substr($key, strlen($field->attribute()))] = $fieldRules;
            }
        } elseif ($this->mode === 'fields') {
            $plain = array_filter($this->subFields, fn (Field $field) => ! $field instanceof self);
            $nested = array_filter($this->subFields, fn (Field $field) => $field instanceof self);
            $rules = [...$rules, ...$this->fieldRules($plain, "{$path}.*", $context, $request)];

            foreach (array_keys($rows) as $index) {
                $rules = [...$rules, ...$this->fieldRules($nested, "{$path}.{$index}", $context, $request)];
            }
        } else {
            $keys = array_map(fn (Repeatable $block) => $block->key(), $this->blocks);

            // Each row gets its own block's rules only, so two blocks may share an attribute.
            foreach ($rows as $index => $row) {
                $rules["{$path}.{$index}.type"] = ['required', Rule::in($keys)];
                $fields = is_array($row) ? $this->rowFields($row) : null;
                $rules = [...$rules, ...$this->fieldRules($fields ?? [], "{$path}.{$index}", $context, $request)];
            }
        }

        if ($this->distinct !== null) {
            $rules["{$path}.*.{$this->distinct}"] = [...($rules["{$path}.*.{$this->distinct}"] ?? []), 'distinct'];
        }

        return $rules;
    }

    /**
     * @param  array<Field>  $fields
     * @return array<string, list<mixed>>
     */
    protected function fieldRules(array $fields, string $rowPath, FieldContext $context, ?LatheRequest $request): array
    {
        $rules = [];

        foreach ($fields as $field) {
            if ($field->isComputed()) {
                continue;
            }

            if ($field instanceof self) {
                $rules = [...$rules, ...$field->rulesAt("{$rowPath}.{$field->attribute()}", $context, $request)];

                continue;
            }

            foreach ($field->getRules($context, $request) as $key => $fieldRules) {
                $rules["{$rowPath}.{$key}"] = $fieldRules;
            }
        }

        return $rules;
    }

    protected function maxRows(): int
    {
        $max = $this->options['max'];

        return is_int($max) ? $max : app(ConfigStore::class)->integer('limits.repeater_rows');
    }

    protected function impliedRules(): array
    {
        return array_values(array_filter([
            'array',
            is_int($this->options['min']) ? "min:{$this->options['min']}" : null,
            is_int($this->options['max']) ? "max:{$this->options['max']}" : null,
        ]));
    }

    public function getMessages(): array
    {
        return $this->scoped(fn (Field $field) => $field->getMessages(), parent::getMessages());
    }

    public function getValidationAttributes(): array
    {
        return $this->scoped(fn (Field $field) => $field->getValidationAttributes(), parent::getValidationAttributes());
    }

    /**
     * Sub-field messages or attribute names under wildcard row keys (`specifications.*.name`),
     * which the validator matches against each row's key.
     *
     * @param  Closure(Field): array<string, string>  $read
     * @param  array<string, string>  $scoped
     * @return array<string, string>
     */
    protected function scoped(Closure $read, array $scoped): array
    {
        $fields = $this->mode === 'repeatables'
            ? array_merge(...array_map(fn (Repeatable $block) => $block->getFields(), $this->blocks))
            : $this->subFields;

        foreach ($fields as $field) {
            foreach ($read($field) as $key => $value) {
                $scoped[$this->mode === 'simple' ? "{$this->attribute}.*".substr($key, strlen($field->attribute())) : "{$this->attribute}.*.{$key}"] = $value;
            }
        }

        return $scoped;
    }

    // Payload

    public function schema(FieldContext $context, LatheRequest $request, ?Model $model = null): array
    {
        $field = fn (Field $field) => $field->schema($context, $request);

        return [
            ...parent::schema($context, $request, $model),
            'fields' => array_map($field, $this->subFields),
            'blocks' => array_map(fn (Repeatable $block) => $block->toPayload($field), $this->blocks),
        ];
    }

    public function jsonSerialize(): array
    {
        $field = fn (Field $field) => $field->jsonSerialize();

        return [
            ...parent::jsonSerialize(),
            'mode' => $this->mode,
            'fields' => array_map($field, $this->subFields),
            'blocks' => array_map(fn (Repeatable $block) => $block->toPayload($field), $this->blocks),
        ];
    }
}
