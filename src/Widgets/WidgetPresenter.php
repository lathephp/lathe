<?php

namespace Lathe\Widgets;

use JsonSerializable;
use Lathe\Http\Requests\LatheRequest;

/**
 * Shapes a widget value response: {name, uriKey, component, value}.
 */
final readonly class WidgetPresenter
{
    public function __construct(
        private Widget $widget,
    ) {
        //
    }

    /**
     * @return array{name: string, uriKey: string, component: string, value: mixed}
     */
    public function toResponse(LatheRequest $request): array
    {
        $value = $this->widget->resolve($request);

        return [
            'name' => $this->widget->name(),
            'uriKey' => $this->widget::uriKey(),
            'component' => $this->widget->component(),
            'value' => $value instanceof JsonSerializable ? $value->jsonSerialize() : $value,
        ];
    }
}
