<?php

namespace Lathe\Tests\Fixtures\Resources;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Tests\Fixtures\Models\Swatch as SwatchModel;

/**
 * Swatches keyed by colour, which two swatches can share.
 */
class ColourKeyedSwatch extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(SwatchModel::class)->canonical(false)->title('colour')->allowAll();
    }

    public static function rowKey(): string
    {
        return 'colour';
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }
}
