<?php

use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\Tooltip;
use Lathe\Widgets\Widget;

function tooltipCard(): Widget
{
    return new class extends Widget
    {
        public function calculate(LatheRequest $request): mixed
        {
            return 1;
        }
    };
}

it('sends every tooltip option in the descriptor', function () {
    $tooltip = Tooltip::make()->indicator('line')->hideLabel()->hideIndicator()->label('Orders')
        ->valuePrefix('$')->valueSuffix('kcal')->showTotal();

    expect(tooltipCard()->tooltip($tooltip)->descriptor()['tooltip'])->toBe([
        'indicator' => 'line',
        'hideLabel' => true,
        'hideIndicator' => true,
        'label' => 'Orders',
        'valuePrefix' => '$',
        'valueSuffix' => 'kcal',
        'showTotal' => true,
    ]);
});

it('sends a default tooltip as the dot indicator alone', function () {
    expect(tooltipCard()->tooltip(Tooltip::make())->descriptor()['tooltip'])->toBe(['indicator' => 'dot']);
});

it('pins that the descriptor has no tooltip until one is set', function () {
    expect(tooltipCard()->descriptor())->not->toHaveKey('tooltip');
});
