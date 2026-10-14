<?php

namespace Workbench\App\Lathe\Plugins;

use Lathe\Field\Field;
use Lathe\Layout\Section;
use Lathe\Layout\Tab;
use Lathe\Plugins\Plugin;

/**
 * A plugin with only a settings form, so the panel builds its page, route and sidebar link.
 */
class HelloPlugin extends Plugin
{
    public function name(): string
    {
        return 'Hello Plugin';
    }

    public static function uriKey(): string
    {
        return 'hello-plugin';
    }

    public function settings(): array
    {
        return [
            Field::text('Greeting')->required()->rules('max:255')->help('Shown at the top of the dashboard.'),
            Field::boolean('Enabled'),
            Section::make('Appearance', [
                Field::select('Accent')->options(['blue' => 'Blue', 'green' => 'Green', 'orange' => 'Orange'])->nullable(),
                Field::boolean('Compact'),
            ])->columns(2),
            Tab::group('Advanced', [
                Tab::make('Limits', [
                    Field::number('Max items', 'max_items')->min(1)->rules('integer'),
                ]),
                Tab::make('Danger Zone', [
                    Field::boolean('Debug mode', 'debug_mode'),
                ]),
            ]),
        ];
    }
}
