<?php

namespace Lathe\Field\Relations;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\Contracts\PrimesForPage;
use Lathe\Field\Evaluator;
use Lathe\Field\FieldContext;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Support\ConfigStore;

/**
 * The parent record of a belongsTo relation: a picker in forms, a linked name on index and
 * detail. Required unless nullable().
 */
class BelongsToField extends RelationField implements PrimesForPage
{
    protected string $component = 'belongs-to-field';

    protected bool $viewable = true;

    protected ?Closure $filledCallback = null;

    /** @var array<int|string, bool>|null Whether the user may view each related record of the page, by key. */
    protected ?array $viewableKeys = null;

    public function kind(): string
    {
        return 'belongsTo';
    }

    public function loadsWithRecord(): bool
    {
        return true;
    }

    /**
     * The sort key of a related title column joins the related table: customer.name.
     */
    public function getSortableUriKey(): string
    {
        $related = $this->relatedResource();
        $title = $related !== null ? $related::make()->definition()->getTitle() : null;

        return $this->sortUriKey ?? (is_string($title) ? $this->attribute.'.'.$title : $this->attribute);
    }

    /**
     * @return array{value: mixed, display: string, href: string|null}|null
     */
    public function resolve(object $record, ?string $attribute = null): mixed
    {
        $related = $record instanceof Model ? $this->relatedRecord($record) : null;

        return $related === null ? null : [
            'value' => $related->getKey(),
            'display' => $this->displayFor($related),
            'href' => $this->mayView($related) ? $this->detailPath($related) : null,
        ];
    }

    /**
     * Whether hovering the value opens a card: peeking is on and the related Resource has
     * fields to show in it.
     */
    public function opensPeek(?LatheRequest $request): bool
    {
        $class = $this->relatedResource();

        if ($request === null || $class === null || ! $this->isPeekable($request)) {
            return false;
        }

        return $class::make()->fieldsFor(FieldContext::Peeking, $request)
            ->forContext(FieldContext::Peeking, $request)
            ->authorized($request)
            ->isNotEmpty();
    }

    public function jsonSerialize(): array
    {
        return [...parent::jsonSerialize(), 'peekable' => $this->opensPeek(Evaluator::currentRequest())];
    }

    /**
     * The panel path of the related record's detail page.
     */
    protected function detailPath(Model $related): ?string
    {
        $class = $this->relatedResource();
        $key = $related->getKey();

        if ($class === null || ! (is_int($key) || is_string($key))) {
            return null;
        }

        $base = '/'.trim(Container::getInstance()->make(ConfigStore::class)->string('path'), '/');

        return rtrim($base, '/').'/resources/'.$class::uriKey().'/'.$key;
    }

    /**
     * Check view on every related record of an index page in one batch.
     *
     * @param  Collection<array-key, Model>  $records
     */
    public function primeForPage(Collection $records): void
    {
        $class = $this->relatedResource();

        if (! $this->viewable || $class === null) {
            return;
        }

        $related = $records
            ->map(fn (Model $record) => $record->relationLoaded($this->attribute) ? $record->getRelation($this->attribute) : null)
            ->filter(fn (mixed $model) => $model instanceof Model)
            ->unique(fn (Model $model) => $model->getKey())
            ->values();

        $abilities = Container::getInstance()->make(ResourceAuthorization::class)
            ->abilitiesFor(Evaluator::currentRequest()?->user(), $class, $related, ['view']);

        $this->viewableKeys = array_map(fn (array $row) => $row['view'], $abilities);
    }

    protected function mayView(Model $related): bool
    {
        $class = $this->relatedResource();

        if (! $this->viewable || $class === null) {
            return false;
        }

        $key = $related->getKey();

        if ($this->viewableKeys !== null && (is_int($key) || is_string($key)) && array_key_exists($key, $this->viewableKeys)) {
            return $this->viewableKeys[$key];
        }

        return Container::getInstance()->make(ResourceAuthorization::class)
            ->allows(Evaluator::currentRequest()?->user(), $class, 'view', $related);
    }

    /**
     * @return array{value: mixed, display: string}
     */
    public function cell(Model $model): array
    {
        $value = $this->resolve($model);

        return ['value' => $value, 'display' => $value['display'] ?? ''];
    }

    /**
     * Write the foreign key. The value is the related key, or {value: key} as the form sends it.
     */
    public function fill(LatheRequest $request, object $model, ?string $requestAttribute = null): ?Closure
    {
        if (! $model instanceof Model || ($model->exists && $this->isImmutable($request, $model))) {
            return null;
        }

        $value = $request->input($requestAttribute ?? $this->attribute);
        $value = is_array($value) ? ($value['value'] ?? null) : $value;
        $relation = $model->{$this->attribute}();

        if ($relation instanceof BelongsTo) {
            $model->setAttribute($relation->getForeignKeyName(), $value === '' ? null : $value);
        }

        return null;
    }

    /**
     * Link the value to the related detail page when the user may view it.
     */
    public function viewable(bool $viewable = true): static
    {
        $this->mutate();
        $this->viewable = $viewable;

        return $this;
    }

    public function isViewable(): bool
    {
        return $this->viewable;
    }

    /**
     * Run after the foreign key is written.
     *
     * @param  (Closure(LatheRequest, Model): mixed)|null  $callback
     */
    public function filled(?Closure $callback): static
    {
        $this->mutate();
        $this->filledCallback = $callback;

        return $this;
    }

    public function getFilledCallback(): ?Closure
    {
        return $this->filledCallback;
    }
}
