<?php

namespace Lathe\Field;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * One line of text with a style: heading, subtitle, small or base. It stands alone on an
 * index column or inside a StackField.
 */
class LineField extends Field
{
    protected string $component = 'line-field';

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->options += ['style' => 'base', 'extraClasses' => ''];
        $this->exceptOnForms();
    }

    public function asHeading(): static
    {
        return $this->option('style', 'heading');
    }

    public function asSubTitle(): static
    {
        return $this->option('style', 'subtitle');
    }

    public function asSmall(): static
    {
        return $this->option('style', 'small');
    }

    public function asBase(): static
    {
        return $this->option('style', 'base');
    }

    /**
     * CSS class names applied as given; the host's CSS supplies any new one.
     *
     * @param  list<string>|string  $classes
     */
    public function extraClasses(array|string $classes): static
    {
        return $this->option('extraClasses', is_array($classes) ? implode(' ', $classes) : $classes);
    }

    /**
     * @return array{value: mixed, display: string, style: mixed, extraClasses: mixed}
     */
    public function cell(Model $model): array
    {
        return [...parent::cell($model), 'style' => $this->options['style'], 'extraClasses' => $this->options['extraClasses']];
    }
}
