<?php

namespace Lathe\Tests\Fixtures\Resources;

use Lathe\Actions\Action;
use Lathe\Actions\DestructiveAction;
use Lathe\Actions\Export\ExportAction;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Tests\Fixtures\Lenses\AllParents;
use Lathe\Tests\Fixtures\Models\Matrix\MatrixParents;
use Lathe\Tests\Fixtures\Widgets\OrderCount;

/**
 * The matrix parent with every kind of thing a policy can allow, and no policy of its own:
 * the model's policy is whatever a test registers with the gate.
 */
class ClosedParent extends MatrixParents
{
    public static ?string $policy = null;

    public static function uriKey(): string
    {
        return 'closed-parents';
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::text('Name')->required()->inline(), ...array_slice(parent::fields($request), 1)];
    }

    public function lenses(LatheRequest $request): array
    {
        return [new AllParents];
    }

    public function widgets(LatheRequest $request): array
    {
        return [new OrderCount];
    }

    public function actions(LatheRequest $request): array
    {
        return [
            ExportAction::make(),
            Action::make()->withName('Tag')->withUriKey('tag'),
            DestructiveAction::make()->withName('Purge')->withUriKey('purge'),
        ];
    }
}
