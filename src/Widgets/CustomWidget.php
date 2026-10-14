<?php

namespace Lathe\Widgets;

use Lathe\Enums\CardWidth;
use Lathe\Http\Requests\LatheRequest;

/**
 * A card drawn by a client component, with no value from the server.
 */
class CustomWidget extends Widget
{
    protected ?string $component = 'custom-widget';

    protected string $height = 'dynamic';

    protected ?string $clientComponent = null;

    public function __construct(protected string $name = '')
    {
        parent::__construct();
    }

    public static function make(string $name = ''): static
    {
        return new static($name);
    }

    public function name(): string
    {
        return $this->name !== '' ? $this->name : parent::name();
    }

    /**
     * The key of the client component that draws the card.
     */
    public function withComponent(string $key): static
    {
        $this->clientComponent = $key;

        return $this;
    }

    /**
     * @param  'fixed'|'double'|'dynamic'  $height
     */
    public function height(string $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function dynamicHeight(): static
    {
        return $this->height('dynamic');
    }

    public function fixedHeight(): static
    {
        return $this->height('fixed');
    }

    /**
     * Spans the full width and sizes to its content.
     */
    public function fullWidth(): static
    {
        return $this->width(CardWidth::Full)->dynamicHeight();
    }

    public function requestsValue(): bool
    {
        return false;
    }

    public function calculate(LatheRequest $request): mixed
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function descriptor(): array
    {
        return [
            ...parent::descriptor(),
            ...($this->clientComponent === null ? [] : ['clientComponent' => $this->clientComponent]),
        ];
    }
}
