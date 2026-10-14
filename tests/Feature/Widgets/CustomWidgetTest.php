<?php

use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\CustomWidget;

it('sends the client component key and makes no value request', function () {
    $card = CustomWidget::make('Clock')->withComponent('workbench-clock');

    expect($card->descriptor())->toMatchArray(['name' => 'Clock', 'component' => 'custom-widget', 'clientComponent' => 'workbench-clock', 'requestsValue' => false])
        ->and($card->requestsValue())->toBeFalse()
        ->and($card->resolve(LatheRequest::create('/')))->toBeNull();
});

it('sizes to its content until a fixed height is asked for', function () {
    expect(CustomWidget::make('Clock')->descriptor()['height'])->toBe('dynamic')
        ->and(CustomWidget::make('Clock')->height('fixed')->descriptor()['height'])->toBe('fixed')
        ->and(CustomWidget::make('Clock')->height('double')->descriptor()['height'])->toBe('double');
});

it('takes the full width with a dynamic height, and fixedHeight() resets the height', function () {
    $card = CustomWidget::make('Clock')->height('fixed')->fullWidth();

    expect($card->descriptor())->toMatchArray(['width' => 'full', 'height' => 'dynamic'])
        ->and($card->fixedHeight()->descriptor()['height'])->toBe('fixed')
        ->and($card->dynamicHeight()->descriptor()['height'])->toBe('dynamic');
});

it('pins that the descriptor has no client component key until one is set', function () {
    expect(CustomWidget::make('Clock')->descriptor())->not->toHaveKey('clientComponent');
});
