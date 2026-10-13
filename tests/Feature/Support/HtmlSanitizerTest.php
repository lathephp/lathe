<?php

use Lathe\Support\HtmlSanitizer;
use Lathe\Support\SafeHtml;

it('strips the xss payloads', function (string $payload, string $expected) {
    expect(HtmlSanitizer::clean($payload))->toBe($expected);
})->with('xss_payloads');

it('keeps every allowed element', function (string $html) {
    expect(HtmlSanitizer::clean($html))->toBe($html);
})->with([
    '<p>a<br />b</p><hr />',
    '<strong>a</strong><b>a</b><em>a</em><i>a</i><u>a</u><s>a</s><del>a</del><ins>a</ins>',
    '<sub>a</sub><sup>a</sup><code>a</code><pre>a</pre><blockquote>a</blockquote>',
    '<ul><li>a</li></ul><ol><li>a</li></ol>',
    '<h1>a</h1><h2>a</h2><h3>a</h3><h4>a</h4><h5>a</h5><h6>a</h6>',
    '<table><thead><tr><th>a</th></tr></thead><tbody><tr><td>a</td></tr></tbody></table>',
    '<figure><img src="/a.png" alt="a" /><figcaption>a</figcaption></figure><span>a</span>',
]);

it('keeps the attributes on the allow-list', function (string $html) {
    expect(HtmlSanitizer::clean($html))->toBe($html);
})->with([
    'link' => '<a href="https://example.com" title="t">x</a>',
    'image' => '<img src="https://example.com/a.png" alt="a" title="t" width="10" height="20" />',
    'cell' => '<table><tbody><tr><td colspan="2" rowspan="3" align="left">x</td><th colspan="2" rowspan="3" align="left">y</th></tr></tbody></table>',
    'list start' => '<ol start="3"><li>a</li></ol>',
    'code language' => '<pre class="language-php"><code class="language-php">a</code></pre>',
    'mailto' => '<a href="mailto:a&#64;example.com">x</a>',
    'tel' => '<a href="tel:&#43;123">x</a>',
    'relative link' => '<a href="/orders/1">x</a>',
]);

it('drops what the allow-list does not name', function (string $html, string $expected) {
    expect(HtmlSanitizer::clean($html))->toBe($expected);
})->with([
    'other class on code' => ['<code class="evil">a</code>', '<code>a</code>'],
    'class on a paragraph' => ['<p class="language-php">a</p>', '<p>a</p>'],
    'class with a suffix' => ['<code class="language-php evil">a</code>', '<code>a</code>'],
    'data image' => ['<img src="data:image/png;base64,AAAA" />', '<img />'],
    'mailto image' => ['<img src="mailto:a@example.com" />', '<img />'],
    'form' => ['<form action="/x"><input name="a"></form>', ''],
]);

it('adds rel to a link that opens a new tab', function () {
    expect(HtmlSanitizer::clean('<a href="https://example.com" target="_blank">x</a>'))
        ->toBe('<a href="https://example.com" target="_blank" rel="noopener noreferrer">x</a>')
        ->and(HtmlSanitizer::clean('<a href="https://example.com" target="_blank" rel="opener">x</a>'))
        ->toBe('<a href="https://example.com" target="_blank" rel="noopener noreferrer">x</a>');
});

it('leaves a link without a target alone', function () {
    expect(HtmlSanitizer::clean('<a href="https://example.com" rel="nofollow">x</a>'))
        ->toBe('<a href="https://example.com" rel="nofollow">x</a>');
});

it('does not cut a long document', function () {
    $html = str_repeat('<p>abc</p>', 10_000);

    expect(HtmlSanitizer::clean($html))->toBe($html);
});

it('never throws on malformed input', function (?string $html) {
    expect(HtmlSanitizer::clean($html))->toBeString();
})->with([
    null,
    '',
    '<',
    '<<p>>',
    '<p',
    '<a href="',
    '</p></div></a>',
    '<p><b>unclosed',
    "<p>\0</p>",
    "\xff\xfe<p>bad utf8</p>",
    '<table><td><a target="_blank">',
]);

it('serialises a safe html value as a branded object holding clean html', function () {
    $value = SafeHtml::from('<p onclick="x()">a</p><script>b</script>');

    expect(json_encode($value))->toBe('{"__html":"<p>a<\/p>"}')
        ->and($value->toHtml())->toBe('<p>a</p>');
});
