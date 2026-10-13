<?php

namespace Lathe\Support;

use Dom\HTMLDocument;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonySanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * The one place HTML is made safe. Every stored or rendered HTML value passes through
 * clean(): only the elements and attributes below survive, so scripts, event handlers,
 * styles, svg, iframes, forms and data: URLs never reach a page.
 */
class HtmlSanitizer
{
    /** Elements that keep no attribute. */
    private const PLAIN = [
        'p', 'br', 'hr', 'strong', 'b', 'em', 'i', 'u', 's', 'del', 'ins', 'sub', 'sup', 'blockquote',
        'ul', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'table', 'thead', 'tbody', 'tr', 'figure',
        'figcaption', 'span',
    ];

    /** Elements and the attributes they keep; code and pre keep class through languageClass(). */
    private const WITH_ATTRIBUTES = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'th' => ['colspan', 'rowspan', 'align'],
        'td' => ['colspan', 'rowspan', 'align'],
        'ol' => ['start'],
        'code' => ['class'],
        'pre' => ['class'],
    ];

    private static ?SymfonySanitizer $sanitizer = null;

    /**
     * Returns the html with everything off the allow-list removed. Never throws: a
     * malformed or empty input gives a string.
     */
    public static function clean(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        $clean = (self::$sanitizer ??= self::build())->sanitize($html);

        return str_contains($clean, '_blank') ? self::secureNewTabLinks($clean) : $clean;
    }

    private static function build(): SymfonySanitizer
    {
        $config = new HtmlSanitizerConfig;

        foreach (self::PLAIN as $element) {
            $config = $config->allowElement($element);
        }

        foreach (self::WITH_ATTRIBUTES as $element => $attributes) {
            $config = $config->allowElement($element, $attributes);
        }

        $config = $config
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->allowMediaSchemes(['http', 'https'])
            ->allowRelativeMedias()
            ->withAttributeSanitizer(self::languageClass())
            // The default of 20000 characters cuts a long document without a word.
            ->withMaxInputLength(10_000_000);

        return new SymfonySanitizer($config);
    }

    /**
     * A link that opens a new tab gets rel="noopener noreferrer" whatever rel it came with.
     * Symfony cannot add one attribute because of another, so this runs on its clean output.
     */
    private static function secureNewTabLinks(string $clean): string
    {
        $document = HTMLDocument::createFromString('<body>'.$clean, LIBXML_NOERROR);

        foreach ($document->querySelectorAll('a[target="_blank"]') as $link) {
            $link->setAttribute('rel', 'noopener noreferrer');
        }

        $body = $document->body;

        return $body === null ? $clean : $body->innerHTML;
    }

    /**
     * Keeps a class attribute on code and pre only when it names a highlighting language.
     */
    private static function languageClass(): AttributeSanitizerInterface
    {
        return new class implements AttributeSanitizerInterface
        {
            public function getSupportedElements(): array
            {
                return ['code', 'pre'];
            }

            public function getSupportedAttributes(): array
            {
                return ['class'];
            }

            public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
            {
                return preg_match('/^language-[a-z0-9]+$/D', $value) === 1 ? $value : null;
            }
        };
    }
}
