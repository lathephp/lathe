<?php

namespace Lathe\Field;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Lathe\Http\Requests\LatheRequest;

/**
 * Several lines of text, hidden from the index. A value over 10 lines collapses unless
 * alwaysShow() or shouldShow() says to open it.
 */
class TextareaField extends TextField
{
    protected string $component = 'textarea-field';

    protected ?Closure $shouldShow = null;

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);

        $this->options += ['rows' => 4, 'alwaysShow' => false];
        $this->hideFromIndex();
    }

    public function rows(int $rows): static
    {
        return $this->option('rows', $rows);
    }

    /**
     * Never collapse a long value.
     */
    public function alwaysShow(bool $always = true): static
    {
        return $this->option('alwaysShow', $always);
    }

    /**
     * Decide per record whether a long value shows open: fn ($record).
     */
    public function shouldShow(Closure $callback): static
    {
        $this->mutate();
        $this->shouldShow = $callback;

        return $this;
    }

    public function schema(FieldContext $context, LatheRequest $request, ?Model $model = null): array
    {
        return [
            ...parent::schema($context, $request, $model),
            'expanded' => $this->shouldShow !== null && (bool) $this->evaluate($this->shouldShow, ['record' => $model, 'request' => $request], [$model]),
        ];
    }
}
