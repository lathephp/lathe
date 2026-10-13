<?php

use Illuminate\Database\Eloquent\Model;
use Lathe\Field\Field;
use Lathe\Support\SafeHtml;

class HardeningThing extends Model
{
    protected $guarded = [];

    protected $casts = ['body' => 'array'];
}

it('takes no page or script file on a field that names no types', function (string $name, string $mime, bool $accepted) {
    expect(Field::file('Doc')->accepts($name, $mime))->toBe($accepted)
        ->and(Field::file('Doc')->acceptedTypes('text/html')->accepts('page.html', 'text/html'))->toBeTrue();
})->with([
    'html' => ['page.html', 'text/html', false],
    'svg' => ['logo.svg', 'image/svg+xml', false],
    'js' => ['app.js', 'text/javascript', false],
    'pdf' => ['doc.pdf', 'application/pdf', true],
    'png' => ['a.png', 'image/png', true],
]);

it('never passes a stored __html object through as markup', function (Closure $make) {
    $cell = $make()->cell(new HardeningThing(['body' => ['__html' => '<img src=x onerror=alert(1)>']]));

    // Text is shown as text; only a SafeHtml reaches the page as markup, and the sanitizer made it.
    if ($cell['value'] instanceof SafeHtml) {
        expect($cell['value']->toHtml())->not->toContain('onerror');
    } else {
        expect($cell['value'])->toBeString();
    }
})->with([
    'text' => [fn () => Field::text('Body')],
    'text asHtml' => [fn () => Field::text('Body')->asHtml()],
    'markdown' => [fn () => Field::markdown('Body')],
    'tiptap' => [fn () => Field::tiptap('Body')],
]);

it('strips a script tag split around another one', function () {
    $html = Field::markdown('Body')->previewFor('a <scr<script></script>ipt>alert(1)</script> b')->toHtml();

    expect($html)->not->toContain('alert(1)')->not->toContain('<script');
});
