<?php

namespace Lathe\Field;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Support\Traits\Tappable;
use InvalidArgumentException;
use JsonSerializable;
use Lathe\Filters\FieldFilter;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Summaries\Summary;
use LogicException;

/**
 * A column of a Resource: where its value comes from and which component draws it.
 * Static schema: it holds no record and no value, and it is frozen once fields() returns.
 *
 * @phpstan-consistent-constructor
 */
class Field implements JsonSerializable
{
    use Conditionable, Macroable, Tappable;

    protected string $component = 'text';

    protected string $attribute;

    /** The closure that computes the value of a field with no column. */
    protected ?Closure $computed = null;

    protected bool $sortable = false;

    protected bool $filterable = false;

    protected int $filterScale = 1;

    /** @var list<Summary> */
    protected array $summaries = [];

    protected bool $frozen = false;

    /**
     * Whether the field shows in each context: a bool, or a Closure deciding per request.
     *
     * @var array<value-of<FieldContext>, bool|Closure>
     */
    protected array $visibility = [
        'index' => true, 'detail' => true, 'create' => true, 'update' => true, 'preview' => false, 'peeking' => false,
    ];

    /** @var array<value-of<FieldContext>, bool> Contexts whose Closure answers "hide" rather than "show". */
    protected array $hides = [];

    protected ?Closure $seeCallback = null;

    /** @var \WeakMap<LatheRequest, bool>|null */
    protected ?\WeakMap $seen = null;

    /**
     * The presentation modifiers, sent in the payload as they are.
     *
     * @var array<string, mixed>
     */
    protected array $options = [
        'nullable' => false,
        'stacked' => false,
        'fullWidth' => false,
        'columnSpan' => null,
        'indexWidth' => null,
        'borderless' => false,
        'compact' => false,
        'textAlign' => 'left',
        'placeholder' => null,
        'help' => null,
        'helpWidth' => null,
        'wrapping' => false,
        'section' => null,
        'autocomplete' => null,
    ];

    /** @var array<string, mixed> */
    protected array $meta = [];

    protected FieldLifecycle $lifecycle;

    protected ?Closure $resolveCallback = null;

    protected ?Closure $displayCallback = null;

    protected ?Closure $fillCallback = null;

    protected bool|Closure $readonly = false;

    protected bool|Closure $required = false;

    protected bool|Closure $immutable = false;

    protected mixed $default = null;

    /** @var array<int, mixed>|Closure */
    protected array|Closure $nullValues = [''];

    /** The context closures read while this copy resolves; see withContext(). */
    protected ?FieldContext $context = null;

    /**
     * @param  string|(Closure(Model): mixed)|null  $attribute  a Closure makes a computed field
     * @param  (Closure(Model): (string|int|float|bool|null))|null  $resolver
     */
    public function __construct(
        protected string $label,
        string|Closure|null $attribute = null,
        protected ?Closure $resolver = null,
    ) {
        if ($attribute instanceof Closure) {
            $this->computed = $attribute;
            $this->visibility['create'] = $this->visibility['update'] = false;
        }

        $this->attribute = is_string($attribute) ? $attribute : Str::snake($label);
        $this->lifecycle = new DefaultLifecycle;
    }

    /**
     * @param  string|(Closure(Model): mixed)|null  $attribute  a Closure makes a computed field
     * @param  (Closure(Model): (string|int|float|bool|null))|null  $resolver
     */
    public static function make(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null): static
    {
        return new static($label, $attribute, $resolver);
    }

    /**
     * The record key: sortable and off the forms.
     */
    public static function id(string $label = 'ID', string $attribute = 'id'): IdField
    {
        return new IdField($label, $attribute);
    }

    /**
     * Lock the definition. A modifier called afterwards throws, so a field cannot carry
     * one request's state into the next.
     *
     * @internal
     */
    public function freeze(): static
    {
        $this->frozen = true;

        return $this;
    }

    public function isFrozen(): bool
    {
        return $this->frozen;
    }

    /**
     * Every modifier calls this first.
     */
    protected function mutate(): void
    {
        if ($this->frozen) {
            throw new LogicException("The field [{$this->label}] is frozen: modify it inside fields(), not after.");
        }
    }

    public function component(string $component): static
    {
        $this->mutate();
        $this->component = $component;

        return $this;
    }

    public function getComponent(): string
    {
        return $this->component;
    }

    public function isComputed(): bool
    {
        return $this->computed !== null;
    }

    public function attribute(): string
    {
        return $this->attribute;
    }

    /**
     * Let the index sort on this field. Only a field whose attribute is a real column (or a
     * column of a belongsTo relation) should be marked; a computed field never sorts.
     */
    public function sortable(bool $sortable = true): static
    {
        $this->mutate();
        $this->sortable = $sortable;

        return $this;
    }

    public function isSortable(): bool
    {
        return $this->sortable && ! $this->isComputed();
    }

    /**
     * Generate a range filter for this field. A scale above 1 lets the filter's inputs take the
     * shown amount while the URL and the query keep the stored value: 100 for cents shown as amounts.
     */
    public function filterable(bool $filterable = true, int $scale = 1): static
    {
        $this->mutate();

        if ($filterable && $this->isComputed()) {
            throw new LogicException("The computed field [{$this->label}] has no column to filter on.");
        }

        $this->filterable = $filterable;
        $this->filterScale = max(1, $scale);

        return $this;
    }

    public function isFilterable(): bool
    {
        return $this->filterable;
    }

    /**
     * Total this field's column in the index footer, over all records and over the page.
     */
    public function summarize(Summary ...$summaries): static
    {
        $this->mutate();
        $this->summaries = array_values($summaries);

        return $this;
    }

    /**
     * @return list<Summary>
     */
    public function getSummaries(): array
    {
        return $this->summaries;
    }

    public function makeFieldFilter(): FieldFilter
    {
        return new FieldFilter($this->label, $this->attribute, $this->filterScale);
    }

    /**
     * The token this field answers to in ?sort=: its attribute (total_cents, customer.name).
     */
    public function sortableUriKey(): string
    {
        return $this->attribute;
    }

    /**
     * The cell key: the attribute, or the relation name of a dotted one (customer.name gives customer).
     */
    public function key(): string
    {
        return Str::before($this->attribute, '.');
    }

    public function label(): string
    {
        return $this->label;
    }

    // Visibility

    public function showOnIndex(bool|Closure $show = true): static
    {
        return $this->setVisibility(FieldContext::Index, $show);
    }

    public function showOnDetail(bool|Closure $show = true): static
    {
        return $this->setVisibility(FieldContext::Detail, $show);
    }

    public function showOnCreating(bool|Closure $show = true): static
    {
        return $this->setVisibility(FieldContext::Create, $show);
    }

    public function showOnUpdating(bool|Closure $show = true): static
    {
        return $this->setVisibility(FieldContext::Update, $show);
    }

    public function showOnPreview(bool|Closure $show = true): static
    {
        return $this->setVisibility(FieldContext::Preview, $show);
    }

    public function showWhenPeeking(bool|Closure $show = true): static
    {
        return $this->setVisibility(FieldContext::Peeking, $show);
    }

    public function hideFromIndex(bool|Closure $hide = true): static
    {
        return $this->setVisibility(FieldContext::Index, $hide, hide: true);
    }

    public function hideFromDetail(bool|Closure $hide = true): static
    {
        return $this->setVisibility(FieldContext::Detail, $hide, hide: true);
    }

    public function hideWhenCreating(bool|Closure $hide = true): static
    {
        return $this->setVisibility(FieldContext::Create, $hide, hide: true);
    }

    public function hideWhenUpdating(bool|Closure $hide = true): static
    {
        return $this->setVisibility(FieldContext::Update, $hide, hide: true);
    }

    public function hideFromPreview(bool|Closure $hide = true): static
    {
        return $this->setVisibility(FieldContext::Preview, $hide, hide: true);
    }

    public function hideWhenPeeking(bool|Closure $hide = true): static
    {
        return $this->setVisibility(FieldContext::Peeking, $hide, hide: true);
    }

    public function onlyOnIndex(): static
    {
        return $this->onlyOn(FieldContext::Index);
    }

    public function onlyOnDetail(): static
    {
        return $this->onlyOn(FieldContext::Detail);
    }

    public function onlyOnForms(): static
    {
        return $this->onlyOn(FieldContext::Create, FieldContext::Update);
    }

    public function exceptOnForms(): static
    {
        return $this->onlyOn(FieldContext::Index, FieldContext::Detail);
    }

    public function onlyOnPreview(): static
    {
        return $this->onlyOn(FieldContext::Preview);
    }

    /**
     * Whether the field shows in a context. Index closures get no record: one column set per page.
     */
    public function isShownOn(FieldContext $context, ?LatheRequest $request = null, mixed $model = null): bool
    {
        if ($context->isForm() && $this->isComputed()) {
            return false;
        }

        $show = $this->visibility[$context->value];

        if (is_bool($show)) {
            return $show;
        }

        $answer = (bool) Evaluator::for($request)->call($show, [
            'record' => $context === FieldContext::Index ? null : $model,
            'request' => $request,
            'field' => $this,
            'context' => $context,
        ], [$request, $context === FieldContext::Index ? null : $model]);

        return ($this->hides[$context->value] ?? false) ? ! $answer : $answer;
    }

    protected function onlyOn(FieldContext ...$contexts): static
    {
        $this->mutate();

        foreach (FieldContext::cases() as $context) {
            $this->visibility[$context->value] = in_array($context, $contexts, true);
            unset($this->hides[$context->value]);
        }

        return $this;
    }

    protected function setVisibility(FieldContext $context, bool|Closure $value, bool $hide = false): static
    {
        $this->mutate();
        $this->visibility[$context->value] = is_bool($value) && $hide ? ! $value : $value;

        if ($value instanceof Closure && $hide) {
            $this->hides[$context->value] = true;
        } else {
            unset($this->hides[$context->value]);
        }

        return $this;
    }

    // Authorization

    /**
     * Show the field only to the users the callback accepts. Runs once per request, never per row.
     */
    public function canSee(Closure $callback): static
    {
        $this->mutate();
        $this->seeCallback = $callback;

        return $this;
    }

    public function canSeeWhen(string $ability, mixed $arguments = []): static
    {
        return $this->canSee(fn (LatheRequest $request) => Gate::forUser($request->user())->check($ability, $arguments));
    }

    public function authorizedToSee(LatheRequest $request): bool
    {
        if ($this->seeCallback === null) {
            return true;
        }

        $this->seen ??= new \WeakMap;

        return $this->seen[$request] ??= (bool) Evaluator::for($request)->call(
            $this->seeCallback,
            ['request' => $request, 'field' => $this],
            [$request],
        );
    }

    // Presentation modifiers, each sent in the payload

    public function nullable(bool $nullable = true): static
    {
        return $this->option('nullable', $nullable);
    }

    public function isNullable(): bool
    {
        return $this->options['nullable'] === true;
    }

    public function stacked(bool $stacked = true): static
    {
        return $this->option('stacked', $stacked);
    }

    public function fullWidth(bool $fullWidth = true): static
    {
        return $this->option('fullWidth', $fullWidth);
    }

    public function columnSpan(?int $span): static
    {
        if ($span !== null && ($span < 1 || $span > 6)) {
            throw new InvalidArgumentException("The field [{$this->label}] spans {$span} columns; a span takes 1 to 6.");
        }

        return $this->option('columnSpan', $span);
    }

    public function getColumnSpan(): ?int
    {
        $span = $this->options['columnSpan'];

        return is_int($span) ? $span : null;
    }

    public function indexWidth(int $pixels): static
    {
        return $this->option('indexWidth', $pixels);
    }

    public function borderless(bool $borderless = true): static
    {
        return $this->option('borderless', $borderless);
    }

    public function compact(bool $compact = true): static
    {
        return $this->option('compact', $compact);
    }

    /**
     * @param  'left'|'center'|'right'  $align
     */
    public function textAlign(string $align): static
    {
        return $this->option('textAlign', $align);
    }

    public function placeholder(string $placeholder): static
    {
        return $this->option('placeholder', $placeholder);
    }

    /**
     * Help text under the input. Plain text: the client never renders it as HTML.
     */
    public function help(string $help): static
    {
        return $this->option('help', $help);
    }

    public function helpWidth(int|string $width): static
    {
        return $this->option('helpWidth', $width);
    }

    /**
     * Wrap an index cell onto at most 5 lines; off shows one line with an ellipsis.
     */
    public function wrapping(bool $wrapping = true): static
    {
        return $this->option('wrapping', $wrapping);
    }

    /**
     * Put the field in the section with this attribute.
     */
    public function withSection(string $section): static
    {
        return $this->option('section', $section);
    }

    public function getSection(): ?string
    {
        $section = $this->options['section'];

        return is_string($section) ? $section : null;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function withMeta(array $meta): static
    {
        $this->mutate();
        $this->meta = [...$this->meta, ...$meta];

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }

    protected function option(string $name, mixed $value): static
    {
        $this->mutate();
        $this->options[$name] = $value;

        return $this;
    }

    // Lifecycle and state

    public function useLifecycle(FieldLifecycle $lifecycle): static
    {
        $this->mutate();
        $this->lifecycle = $lifecycle;

        return $this;
    }

    public function lifecycle(): FieldLifecycle
    {
        return $this->lifecycle;
    }

    /**
     * Replace extract and present: fn ($record, $attribute) returns the wire value.
     */
    public function resolveUsing(Closure $callback): static
    {
        $this->mutate();
        $this->resolveCallback = $callback;

        return $this;
    }

    /**
     * Change what index, detail, preview and peeking show: fn ($value, $attribute). Forms keep the raw value.
     */
    public function displayUsing(Closure $callback): static
    {
        $this->mutate();
        $this->displayCallback = $callback;

        return $this;
    }

    /**
     * Replace parse and persist: fn ($request, $model, $attribute, $requestAttribute) may return a
     * Closure that runs after save.
     */
    public function fillUsing(Closure $callback): static
    {
        $this->mutate();
        $this->fillCallback = $callback;

        return $this;
    }

    public function readonly(bool|Closure $readonly = true): static
    {
        $this->mutate();
        $this->readonly = $readonly;

        return $this;
    }

    public function required(bool|Closure $required = true): static
    {
        $this->mutate();
        $this->required = $required;

        return $this;
    }

    /**
     * Editable on create; on update it shows read-only and fill leaves the stored value.
     */
    public function immutable(bool|Closure $immutable = true): static
    {
        $this->mutate();
        $this->immutable = $immutable;

        return $this;
    }

    /**
     * The create form's starting value; a Closure takes the request.
     */
    public function default(mixed $value): static
    {
        $this->mutate();
        $this->default = $value;

        return $this;
    }

    /**
     * Inputs that save as null on a nullable field, compared with ===; a Closure takes the value.
     *
     * @param  array<int, mixed>|Closure  $values
     */
    public function nullValues(array|Closure $values): static
    {
        $this->mutate();
        $this->nullValues = $values;

        return $this;
    }

    /**
     * @param  list<string>|string|bool  $tokens
     */
    public function autocomplete(array|string|bool $tokens): static
    {
        return $this->option('autocomplete', match (true) {
            $tokens === true => 'on',
            $tokens === false => 'off',
            is_array($tokens) => implode(' ', $tokens),
            default => $tokens,
        });
    }

    public function withoutAutoCompletion(): static
    {
        return $this->autocomplete(false);
    }

    /**
     * A copy whose closures read this context while it resolves. The frozen original is untouched.
     */
    public function withContext(FieldContext $context): static
    {
        $copy = clone $this;
        $copy->context = $context;

        return $copy;
    }

    public function isReadonly(?LatheRequest $request = null, mixed $model = null): bool
    {
        return $this->decide($this->readonly, $request, $model);
    }

    public function isRequired(?LatheRequest $request = null, mixed $model = null): bool
    {
        return $this->decide($this->required, $request, $model);
    }

    public function isImmutable(?LatheRequest $request = null, mixed $model = null): bool
    {
        return $this->decide($this->immutable, $request, $model);
    }

    public function resolveDefaultValue(?LatheRequest $request = null): mixed
    {
        return $this->default instanceof Closure
            ? $this->evaluate($this->default, ['request' => $request], [$request])
            : $this->default;
    }

    protected function decide(bool|Closure $value, ?LatheRequest $request, mixed $model): bool
    {
        return is_bool($value) ? $value : (bool) $this->evaluate($value, ['request' => $request, 'record' => $model], [$request, $model]);
    }

    /**
     * Call a closure of this field with injection: name, type, container, then position.
     *
     * @param  array{record?: mixed, value?: mixed, attribute?: string|null, request?: LatheRequest|null}  $values
     * @param  list<mixed>  $positional
     */
    protected function evaluate(Closure $callback, array $values = [], array $positional = []): mixed
    {
        $request = $values['request'] ?? Evaluator::currentRequest();

        return Evaluator::for($request)->call($callback, [
            'attribute' => $this->attribute,
            ...$values,
            'request' => $request,
            'field' => $this,
            'context' => $this->context,
        ], $positional);
    }

    // Values

    /**
     * The wire value of this field on a record: extract, then present.
     */
    public function resolve(object $record, ?string $attribute = null): mixed
    {
        $attribute ??= $this->attribute;

        if ($this->resolveCallback !== null) {
            return $this->evaluate($this->resolveCallback, ['record' => $record, 'attribute' => $attribute], [$record, $attribute]);
        }

        if ($this->computed !== null) {
            return DefaultLifecycle::wire($this->evaluate($this->computed, ['record' => $record], [$record]));
        }

        if ($this->resolver !== null && $record instanceof Model) {
            return ($this->resolver)($record);
        }

        return $this->lifecycle->present($this->lifecycle->extract($record, $attribute), $record, $attribute);
    }

    /**
     * The value index, detail, preview and peeking show: resolve(), then displayUsing.
     */
    public function resolveForDisplay(object $record, ?string $attribute = null): mixed
    {
        $attribute ??= $this->attribute;
        $value = $this->resolve($record, $attribute);

        return $this->displayCallback === null ? $value : $this->evaluate(
            $this->displayCallback,
            ['record' => $record, 'value' => $value, 'attribute' => $attribute],
            [$value, $attribute],
        );
    }

    /**
     * Write the request value onto the model. The returned Closure runs after save.
     */
    public function fill(LatheRequest $request, object $model, ?string $requestAttribute = null): ?Closure
    {
        $requestAttribute ??= $this->attribute;

        if ($model instanceof Model && $model->exists && $this->isImmutable($request, $model)) {
            return null;
        }

        if ($this->fillCallback !== null) {
            $after = $this->evaluate(
                $this->fillCallback,
                ['request' => $request, 'record' => $model],
                [$request, $model, $this->attribute, $requestAttribute],
            );

            return $after instanceof Closure ? $after : null;
        }

        $value = $this->lifecycle->parse($request, $requestAttribute);

        if ($this->isNullable() && $this->isNullValue($value)) {
            $value = null;
        }

        return $this->lifecycle->persist($model, $this->attribute, $value);
    }

    protected function isNullValue(mixed $value): bool
    {
        return $this->nullValues instanceof Closure
            ? (bool) $this->evaluate($this->nullValues, ['value' => $value], [$value])
            : in_array($value, $this->nullValues, true);
    }

    /**
     * The cell of one record: its value and the text shown when no component draws it.
     *
     * @return array{value: mixed, display: string}
     */
    public function cell(Model $model): array
    {
        $value = $this->resolveForDisplay($model);

        return ['value' => $value, 'display' => match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'Yes' : 'No',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value),
        }];
    }

    /**
     * The schema a form or detail page needs, with the per-request state decided.
     *
     * @return array<string, mixed>
     */
    public function schema(FieldContext $context, LatheRequest $request, ?Model $model = null): array
    {
        $immutable = $context === FieldContext::Update && $this->isImmutable($request, $model);

        return [
            ...$this->jsonSerialize(),
            'readonly' => $immutable || $this->isReadonly($request, $model),
            'required' => $this->isRequired($request, $model),
            'immutable' => $immutable,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'key' => $this->key(),
            'attribute' => $this->attribute,
            'label' => $this->label,
            'component' => $this->component,
            'sortable' => $this->isSortable(),
            'sortKey' => $this->sortableUriKey(),
            'computed' => $this->isComputed(),
            ...$this->options,
            'meta' => (object) $this->meta,
        ];
    }
}
