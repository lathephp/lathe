<?php

namespace Lathe\Field;

use Closure;

/**
 * A masked input. The payload value is always an empty string.
 */
class PasswordField extends Field
{
    protected string $component = 'password-field';

    public function __construct(string $label = 'Password', string|Closure|null $attribute = 'password', ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->lifecycle = new PasswordLifecycle;
    }
}
