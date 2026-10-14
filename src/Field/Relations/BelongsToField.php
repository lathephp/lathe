<?php

namespace Lathe\Field\Relations;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lathe\Http\Requests\LatheRequest;

/**
 * The parent record of a belongsTo relation: a picker in forms, a linked name on index and
 * detail. Required unless nullable().
 */
class BelongsToField extends RelationField
{
    protected string $component = 'belongs-to-field';

    protected bool $viewable = true;

    protected ?Closure $filledCallback = null;

    public function kind(): string
    {
        return 'belongsTo';
    }

    public function loadsWithRecord(): bool
    {
        return true;
    }

    /**
     * @return array{value: mixed, display: string}|null
     */
    public function resolve(object $record, ?string $attribute = null): mixed
    {
        $related = $record instanceof Model ? $this->relatedRecord($record) : null;

        return $related === null ? null : ['value' => $related->getKey(), 'display' => $this->displayFor($related)];
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
