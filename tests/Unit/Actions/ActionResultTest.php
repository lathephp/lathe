<?php

use Lathe\Actions\Action;
use Lathe\Actions\ActionResult;

it('serializes each factory to its effect', function (ActionResult $result, string $json) {
    expect(json_encode($result))->toBe($json);
})->with([
    'message' => fn () => [ActionResult::message('Shipped.'), '{"message":"Shipped.","level":"success","effect":null}'],
    'danger' => fn () => [ActionResult::danger('Out of stock.'), '{"message":"Out of stock.","level":"danger","effect":null}'],
    'redirect' => fn () => [ActionResult::redirect('/orders/12'), '{"message":null,"level":"success","effect":{"type":"redirect","url":"\/orders\/12","newTab":false}}'],
    'redirect in a new tab' => fn () => [ActionResult::redirect('https://example.com', true), '{"message":null,"level":"success","effect":{"type":"redirect","url":"https:\/\/example.com","newTab":true}}'],
    'openInNewTab' => fn () => [ActionResult::openInNewTab('http://example.com/a'), '{"message":null,"level":"success","effect":{"type":"redirect","url":"http:\/\/example.com\/a","newTab":true}}'],
    'visit' => fn () => [ActionResult::visit('/lathe/resources/orders'), '{"message":null,"level":"success","effect":{"type":"visit","path":"\/lathe\/resources\/orders","options":{}}}'],
    'visit with options' => fn () => [ActionResult::visit('/orders', ['preserveScroll' => true]), '{"message":null,"level":"success","effect":{"type":"visit","path":"\/orders","options":{"preserveScroll":true}}}'],
    'download' => fn () => [ActionResult::download('orders.csv', '/exports/orders.csv'), '{"message":null,"level":"success","effect":{"type":"download","name":"orders.csv","url":"\/exports\/orders.csv"}}'],
    'modal' => fn () => [ActionResult::modal('hold-order'), '{"message":null,"level":"success","effect":{"type":"modal","component":"hold-order","data":{}}}'],
    'modal with data' => fn () => [ActionResult::modal('hold-order', ['id' => 12]), '{"message":null,"level":"success","effect":{"type":"modal","component":"hold-order","data":{"id":12}}}'],
    'emit' => fn () => [ActionResult::emit('orders-shipped', ['count' => 2]), '{"message":null,"level":"success","effect":{"type":"emit","event":"orders-shipped","data":{"count":2}}}'],
    'deleted' => fn () => [ActionResult::deleted(), '{"message":"The resource was deleted.","level":"success","effect":{"type":"deleted"}}'],
    'Action::message' => fn () => [Action::message('Done.'), '{"message":"Done.","level":"success","effect":null}'],
    'Action::deleted' => fn () => [Action::deleted(), '{"message":"The resource was deleted.","level":"success","effect":{"type":"deleted"}}'],
]);

it('keeps one message and replaces the first effect with the second', function () {
    $result = ActionResult::redirect('/orders')
        ->withMessage('Shipped.')
        ->withDangerMessage('Two failed.')
        ->withModal('hold-order', ['id' => 3]);

    expect(json_encode($result))
        ->toBe('{"message":"Two failed.","level":"danger","effect":{"type":"modal","component":"hold-order","data":{"id":3}}}');
});

it('chains each effect onto a message', function (ActionResult $result, string $json) {
    expect(json_encode($result))->toBe($json);
})->with([
    'withRedirect' => fn () => [ActionResult::message('Ok.')->withRedirect('/a'), '{"message":"Ok.","level":"success","effect":{"type":"redirect","url":"\/a","newTab":false}}'],
    'usingNewTab' => fn () => [ActionResult::message('Ok.')->withRedirect('/a')->usingNewTab(), '{"message":"Ok.","level":"success","effect":{"type":"redirect","url":"\/a","newTab":true}}'],
    'withVisitOptions' => fn () => [ActionResult::message('Ok.')->withVisitOptions('/b', ['replace' => true]), '{"message":"Ok.","level":"success","effect":{"type":"visit","path":"\/b","options":{"replace":true}}}'],
    'withDownload' => fn () => [ActionResult::message('Ok.')->withDownload('a.csv', '/a.csv'), '{"message":"Ok.","level":"success","effect":{"type":"download","name":"a.csv","url":"\/a.csv"}}'],
    'withEvent' => fn () => [ActionResult::message('Ok.')->withEvent('refresh'), '{"message":"Ok.","level":"success","effect":{"type":"emit","event":"refresh","data":{}}}'],
    'withDeleted' => fn () => [ActionResult::message('Ok.')->withDeleted(), '{"message":"Ok.","level":"success","effect":{"type":"deleted"}}'],
]);

it('refuses a url with a scheme other than http or https', function (string $method, array $args) {
    expect(fn () => ActionResult::message('x')->{$method}(...$args))->toThrow(InvalidArgumentException::class);
})->with([
    'redirect javascript' => ['redirect', ['javascript:alert(1)']],
    'redirect upper case' => ['redirect', ['JavaScript:alert(1)']],
    'redirect with a tab' => ['redirect', ["java\tscript:alert(1)"]],
    'openInNewTab data' => ['openInNewTab', ['data:text/html,<script>alert(1)</script>']],
    'download' => ['download', ['x', 'javascript:alert(1)']],
    'visit' => ['visit', ['javascript:alert(1)']],
    'withRedirect' => ['withRedirect', [' javascript:alert(1)']],
    'withDownload' => ['withDownload', ['x', 'vbscript:x']],
    'withVisitOptions' => ['withVisitOptions', ['javascript:alert(1)']],
]);

it('accepts relative, http and https urls', function (string $url) {
    expect(ActionResult::redirect($url)->jsonSerialize()['effect'])->toBe(['type' => 'redirect', 'url' => $url, 'newTab' => false]);
})->with(['/orders/12', 'orders?page=2', '#top', 'https://example.com/a', 'http://example.com']);
