<?php

namespace Lathe\Field;

use Closure;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;

/**
 * An audio file that plays inline on the detail page and the form. The index leaves it out.
 */
class AudioField extends FileField
{
    protected string $component = 'audio-field';

    /** @var list<string> */
    protected array $acceptedTypes = ['audio/*'];

    /** @var string|(Closure(LatheRequest): string) */
    protected string|Closure $preload = 'auto';

    /**
     * @param  string|(Closure(Model): mixed)|null  $attribute  a Closure makes a computed field
     * @param  (Closure(Model): (string|int|float|bool|null))|null  $resolver
     */
    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);

        $this->hideFromIndex();
    }

    /**
     * How much audio the browser loads before play: auto, metadata or none. A Closure
     * (LatheRequest) may return one of them.
     *
     * @param  string|(Closure(LatheRequest): string)  $strategy
     */
    public function preload(string|Closure $strategy = 'auto'): static
    {
        $this->mutate();

        if (is_string($strategy) && ! in_array($strategy, ['auto', 'metadata', 'none'], true)) {
            throw new InvalidArgumentException(Lathe::__('The preload strategy must be auto, metadata or none.'));
        }

        $this->preload = $strategy;

        return $this;
    }

    public function schema(FieldContext $context, LatheRequest $request, ?Model $model = null): array
    {
        $preload = $this->preload instanceof Closure
            ? $this->evaluate($this->preload, ['request' => $request, 'record' => $model], [$request])
            : $this->preload;

        return [
            ...parent::schema($context, $request, $model),
            'preload' => in_array($preload, ['auto', 'metadata', 'none'], true) ? $preload : 'auto',
        ];
    }

    public function jsonSerialize(): array
    {
        return [...parent::jsonSerialize(), 'preload' => is_string($this->preload) ? $this->preload : 'auto'];
    }
}
