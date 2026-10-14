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
            'The resource plugin component "%s" is used by both %s and %s. Give one of them a distinct component().',
            $component, $first, $second,
        ));
    }

    /**
     * @param  class-string  $resource
     */
    public static function twiceIn(string $component, string $resource): self
    {
        return new self(sprintf('The resource plugin component "%s" is placed twice in %s.', $component, $resource));
    }
}
