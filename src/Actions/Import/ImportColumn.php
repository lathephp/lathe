<?php

namespace Lathe\Actions\Import;

use Closure;
use Illuminate\Support\Str;
use JsonSerializable;

/**
 * One column an import accepts: its label, validation rules, how a cell is cast and
 * which related record it points at.
 */
class ImportColumn implements JsonSerializable
{
    protected ?string $label = null;

    /** @var list<string> */
    protected array $rules = [];

    /** @var array<string, string> */
    protected array $messages = [];

    protected bool $nullable = false;

    /** @var 'boolean'|'numeric'|'date'|null */
    protected ?string $cast = null;

    protected ?int $decimalPlaces = null;

    protected ?string $dateFormat = null;

    /** @var array{name: string, resolveBy: string}|null */
    protected ?array $relationship = null;

    protected ?Closure $castUsing = null;

    protected bool $ignoreAutoMapping = false;

    final public function __construct(protected string $name)
    {
        //
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function rules(string ...$rules): static
    {
        $this->rules = array_values($rules);

        return $this;
    }

    /**
     * @param  array<string, string>  $messages
     */
    public function messages(array $messages): static
    {
        $this->messages = $messages;

        return $this;
    }

    public function nullable(bool $nullable = true): static
    {
        $this->nullable = $nullable;

        return $this;
    }

    public function boolean(): static
    {
        $this->cast = 'boolean';

        return $this;
    }

    public function numeric(?int $decimalPlaces = null): static
    {
        $this->cast = 'numeric';
        $this->decimalPlaces = $decimalPlaces;

        return $this;
    }

    public function date(?string $format = null): static
    {
        $this->cast = 'date';
        $this->dateFormat = $format;

        return $this;
    }

    public function relationship(string $name, string $resolveBy = 'id'): static
    {
        $this->relationship = ['name' => $name, 'resolveBy' => $resolveBy];

        return $this;
    }

    public function castUsing(Closure $callback): static
    {
        $this->castUsing = $callback;

        return $this;
    }

    public function ignoreAutoMapping(): static
    {
        $this->ignoreAutoMapping = true;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label ?? Str::headline($this->name);
    }

    /**
     * @return list<string>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * @return array<string, string>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function isRequired(): bool
    {
        return in_array('required', $this->rules, true);
    }

    public function ignoresAutoMapping(): bool
    {
        return $this->ignoreAutoMapping;
    }

    /**
     * @return array{name: string, resolveBy: string}|null
     */
    public function getRelationship(): ?array
    {
        return $this->relationship;
    }

    /**
     * @return 'boolean'|'numeric'|'date'|null
     */
    public function getCast(): ?string
    {
        return $this->cast;
    }

    public function getDecimalPlaces(): ?int
    {
        return $this->decimalPlaces;
    }

    public function getDateFormat(): ?string
    {
        return $this->dateFormat;
    }

    public function getCastUsing(): ?Closure
    {
        return $this->castUsing;
    }

    /**
     * @return array{name: string, label: string, required: bool}
     */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->getLabel(),
            'required' => $this->isRequired(),
        ];
    }
}
