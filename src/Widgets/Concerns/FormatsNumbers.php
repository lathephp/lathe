<?php

namespace Lathe\Widgets\Concerns;

/**
 * How a result's numbers show on the client: a prefix, a suffix and a format.
 */
trait FormatsNumbers
{
    public ?string $prefix = null;

    public ?string $suffix = null;

    public bool $suffixInflection = true;

    /** @var string|array<string, mixed>|null */
    public string|array|null $format = null;

    public function prefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * The client adds an s to it unless the value is 1 or -1, see withoutSuffixInflection().
     */
    public function suffix(string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    public function withoutSuffixInflection(): static
    {
        $this->suffixInflection = false;

        return $this;
    }

    /**
     * A compact or fraction digits string ('a', '.2'), or Intl.NumberFormat options.
     *
     * @param  string|array<string, mixed>  $format
     */
    public function format(string|array $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function dollars(string $symbol = '$'): static
    {
        return $this->prefix($symbol);
    }

    public function euros(string $symbol = '€'): static
    {
        return $this->prefix($symbol);
    }

    public function currency(string $symbol = '$'): static
    {
        return $this->prefix($symbol);
    }

    /**
     * The options that were set, for a payload that otherwise stays as it was.
     *
     * @return array<string, mixed>
     */
    protected function numberOptions(): array
    {
        return [
            ...($this->prefix === null ? [] : ['prefix' => $this->prefix]),
            ...($this->suffix === null ? [] : ['suffix' => $this->suffix]),
            ...($this->suffixInflection ? [] : ['suffixInflection' => false]),
            ...($this->format === null ? [] : ['format' => $this->format]),
        ];
    }
}
