<?php

namespace Lathe\Exceptions;

class DuplicateResourcePluginException extends LatheException
{
    /**
     * @param  class-string  $first
     * @param  class-string  $second
     */
    public static function forComponent(string $component, string $first, string $second): self
    {
        return new self(sprintf(
            'The resource plugin component "%s" is placed in both %s and %s. Give one of the plugins a distinct component().',
            $component, $first, $second,
        ));
    }
}
