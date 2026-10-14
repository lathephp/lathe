<?php

namespace Lathe\Field\Relations;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Lathe\Http\Requests\LatheRequest;

/**
 * The single child of a hasOne relation: an inline block in the parent form and a panel on
 * detail. ofMany() makes a read-only, detail-only view of a latestOfMany, oldestOfMany or
 * ofMany relation.
 */
class HasOneField extends RelationField
{
    protected string $component = 'has-one-field';

    protected bool $ofMany = false;

    protected ?Closure $alreadyFilledWhen = null;

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->hideFromIndex()->nullable();
    }

    public function kind(): string
    {
        return $this->ofMany ? 'hasOneOfMany' : 'hasOne';
    }

    public function ofMany(): static
    {
        $this->mutate();
        $this->ofMany = true;

        return $this->onlyOnDetail()->readonly();
    }

    public function isOfMany(): bool
    {
        return $this->ofMany;
    }

    /**
     * Replace the "the related row exists" test that hides Create once filled.
     *
     * @param  Closure(LatheRequest, Model): bool  $callback
     */
    public function alreadyFilledWhen(Closure $callback): static
    {
        $this->mutate();
        $this->alreadyFilledWhen = $callback;

        return $this;
    }

    public function getAlreadyFilledWhen(): ?Closure
    {
        return $this->alreadyFilledWhen;
    }

    public function createsThroughPanel(): bool
    {
        return ! $this->ofMany;
    }
}
