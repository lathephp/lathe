<?php

namespace Lathe\Tests\Fixtures\Resources;

use Illuminate\Database\Eloquent\Builder;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Tests\Fixtures\Models\Gadget as GadgetModel;

class Gadget extends Resource
{
    /** @var list<string> */
    public static array $hooks = [];

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(GadgetModel::class)->with(['invoice'])->title('name');
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }

    public static function indexQuery(LatheRequest $request, Builder $query): Builder
    {
        static::$hooks[] = 'indexQuery';

        return $query;
    }

    public static function detailQuery(LatheRequest $request, Builder $query): Builder
    {
        static::$hooks[] = 'detailQuery';

        return $query;
    }

    public static function editQuery(LatheRequest $request, Builder $query): Builder
    {
        static::$hooks[] = 'editQuery';

        return $query;
    }

    public static function relatableQuery(LatheRequest $request, Builder $query): Builder
    {
        static::$hooks[] = 'relatableQuery';

        return $query;
    }

    public static function replicateQuery(LatheRequest $request, Builder $query): Builder
    {
        static::$hooks[] = 'replicateQuery';

        return $query;
    }
}
