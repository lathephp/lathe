<?php

namespace Lathe\Widgets;

/**
 * A card of static text. The client shows the text as it is, never as HTML.
 */
class HelpWidget extends CustomWidget
{
    protected ?string $component = 'help-widget';

    protected string $content = '';

    public function __construct(string $name = 'Help')
    {
        parent::__construct($name);
    }

    public static function make(string $name = 'Help'): static
    {
        return new static($name);
    }

    public function content(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function descriptor(): array
    {
        return [...parent::descriptor(), 'content' => $this->content];
    }
}
