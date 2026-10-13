<?php

namespace Lathe\Field;

use Illuminate\Support\Str;

/**
 * A secret string. Every read-only context shows it masked, so the raw value
 * leaves the server only in the form payload, which resolve() feeds.
 */
class TokenField extends Field
{
    protected string $component = 'token-field';

    protected int $maskStart = 0;

    protected ?int $maskLength = null;

    protected string $maskCharacter = '*';

    /**
     * Show the first $start characters and mask the rest, or $length of them.
     */
    public function mask(int $start, ?int $length = null, string $char = '*'): static
    {
        $this->mutate();
        $this->maskStart = $start;
        $this->maskLength = $length;
        $this->maskCharacter = $char;

        return $this;
    }

    public function resolveForDisplay(object $record, ?string $attribute = null): mixed
    {
        $value = parent::resolveForDisplay($record, $attribute);

        return is_string($value) && $value !== '' ? Str::mask($value, $this->maskCharacter, $this->maskStart, $this->maskLength) : $value;
    }
}
