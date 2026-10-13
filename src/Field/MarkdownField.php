<?php

namespace Lathe\Field;

use Closure;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Lathe\Field\Contracts\PreviewsContent;
use Lathe\Support\SafeHtml;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Parser\Block\HtmlBlockStartParser;
use League\CommonMark\Extension\CommonMark\Parser\Inline\CloseBracketParser;
use League\CommonMark\Extension\CommonMark\Parser\Inline\EscapableParser;
use League\CommonMark\Extension\CommonMark\Parser\Inline\HtmlInlineParser;
use League\CommonMark\Extension\CommonMark\Parser\Inline\OpenBracketParser;
use League\CommonMark\Extension\CommonMark\Renderer\Block\HtmlBlockRenderer;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\HtmlInlineRenderer;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\LinkRenderer;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\Inline\NewlineParser;
use League\CommonMark\Renderer\Block\DocumentRenderer;
use League\CommonMark\Renderer\Block\ParagraphRenderer;
use League\CommonMark\Renderer\Inline\NewlineRenderer;
use League\CommonMark\Renderer\Inline\TextRenderer;

/**
 * Markdown text, stored as typed and hidden from the index. Detail pages and the editor
 * preview show it rendered: raw HTML and unsafe links are dropped, then the result
 * passes through the one sanitizer.
 */
class MarkdownField extends Field implements PreviewsContent
{
    /** @var list<string> */
    protected const array PRESETS = ['default', 'commonmark', 'zero'];

    protected string $component = 'markdown-field';

    protected string $preset = 'default';

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->hideFromIndex();
    }

    /**
     * default: CommonMark with GFM tables, strike-through, autolinks and task lists;
     * commonmark: the core only; zero: paragraphs, breaks and links only.
     */
    public function preset(string $preset): static
    {
        $this->mutate();

        if (! in_array($preset, self::PRESETS, true)) {
            throw new InvalidArgumentException("Unknown Markdown preset [{$preset}]: use default, commonmark or zero.");
        }

        $this->preset = $preset;

        return $this;
    }

    public function previewFor(string $value): SafeHtml
    {
        return SafeHtml::from((new MarkdownConverter($this->environment()))->convert(self::withoutScriptBodies($value))->getContent());
    }

    /**
     * html_input strip drops a raw tag but keeps the text inside it, so an inline
     * <script>alert(1)</script> would show "alert(1)". Script and style bodies go first;
     * this only ever removes text, and strip and the allow-list still run after it.
     */
    protected static function withoutScriptBodies(string $value): string
    {
        return (string) preg_replace('#<(script|style)\b[^>]*>.*?(</\1\s*>|$)#is', '', $value);
    }

    /**
     * The detail value is the rendered HTML; the form keeps resolve(), the source.
     *
     * @return array{value: mixed, display: string}
     */
    public function cell(Model $model): array
    {
        $value = $this->resolveForDisplay($model);

        if (! is_string($value)) {
            return parent::cell($model);
        }

        $html = $this->previewFor($value);

        return ['value' => $html, 'display' => trim(html_entity_decode(strip_tags($html->toHtml()), ENT_QUOTES | ENT_HTML5))];
    }

    protected function environment(): Environment
    {
        $config = ['html_input' => 'strip', 'allow_unsafe_links' => false];

        return match ($this->preset) {
            'commonmark' => Environment::createCommonMarkEnvironment($config),
            'zero' => $this->zeroEnvironment($config),
            default => Environment::createGFMEnvironment($config),
        };
    }

    /**
     * Paragraphs, breaks and links. The HTML parsers stay so that raw HTML is stripped
     * like in the other presets, not shown as text.
     *
     * @param  array<string, mixed>  $config
     */
    protected function zeroEnvironment(array $config): Environment
    {
        $environment = new Environment($config);
        $environment
            ->addBlockStartParser(new HtmlBlockStartParser, 40)
            ->addInlineParser(new NewlineParser, 200)
            ->addInlineParser(new EscapableParser, 80)
            ->addInlineParser(new HtmlInlineParser, 40)
            ->addInlineParser(new CloseBracketParser, 30)
            ->addInlineParser(new OpenBracketParser, 20)
            ->addRenderer(Document::class, new DocumentRenderer)
            ->addRenderer(Paragraph::class, new ParagraphRenderer)
            ->addRenderer(HtmlBlock::class, new HtmlBlockRenderer)
            ->addRenderer(Text::class, new TextRenderer)
            ->addRenderer(Newline::class, new NewlineRenderer)
            ->addRenderer(HtmlInline::class, new HtmlInlineRenderer)
            ->addRenderer(Link::class, new LinkRenderer);

        return $environment;
    }
}
