<?php

namespace Lathe\Field\Relations;

use Closure;
use Illuminate\Database\Eloquent\Model;
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
