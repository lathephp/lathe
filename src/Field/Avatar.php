<?php

namespace Lathe\Field;

use JsonSerializable;

/**
 * The small picture beside a record's title: an image URL, or the initials the client draws instead.
 */
class Avatar implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $style
     */
    public function __construct(
        public readonly ?string $url,
        public readonly ?string $initials,
        public readonly array $style,
        public readonly bool $rounded,
    ) {
        //
    }

    /**
     * The first letters of up to two words, upper case: "Admin User Extra" gives "AU".
     */
    public static function initialsOf(string $text): ?string
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        if ($words === false || $words === []) {
            return null;
        }

        return mb_strtoupper(implode('', array_map(fn (string $word) => mb_substr($word, 0, 1), array_slice($words, 0, 2))));
    }

    /**
     * @return array{url: string|null, initials: string|null, style: array<string, mixed>, rounded: bool}
     */
    public function jsonSerialize(): array
    {
        return ['url' => $this->url, 'initials' => $this->initials, 'style' => $this->style, 'rounded' => $this->rounded];
    }
}
