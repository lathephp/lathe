<?php

namespace Lathe\Support;

use JsonSerializable;

/**
 * HTML that has been through HtmlSanitizer, as it travels to the client.
 *
 * On the wire it is the object {"__html": "<clean html>"}. The TypeScript SafeHtml type
 * in @lathephp/core mirrors that shape and brands it, so the only way to render HTML is
 * the SafeHtml component. The constructor is private: from() always cleans.
 */
class SafeHtml implements JsonSerializable
{
    private function __construct(private readonly string $html) {}

    public static function from(?string $html): self
    {
        return new self(HtmlSanitizer::clean($html));
    }

    public function toHtml(): string
    {
        return $this->html;
    }

    /**
     * @return array{__html: string}
     */
    public function jsonSerialize(): array
    {
        return ['__html' => $this->html];
    }
}
