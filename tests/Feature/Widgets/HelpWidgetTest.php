<?php

use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\HelpWidget;

it('sends the content as it was written, tags and line breaks included', function () {
    $card = HelpWidget::make('Help')->content("Line 1\nLine 2 <b>x</b>");

    expect($card->descriptor())->toMatchArray(['content' => "Line 1\nLine 2 <b>x</b>", 'name' => 'Help', 'component' => 'help-widget']);
});

it('makes no value request', function () {
    $card = HelpWidget::make('Help');

    expect($card->requestsValue())->toBeFalse()
        ->and($card->descriptor()['requestsValue'])->toBeFalse()
        ->and($card->resolve(LatheRequest::create('/')))->toBeNull();
});

it('sizes to its content and takes the name Help by default', function () {
    expect(HelpWidget::make()->descriptor())->toMatchArray(['height' => 'dynamic', 'name' => 'Help']);
});
