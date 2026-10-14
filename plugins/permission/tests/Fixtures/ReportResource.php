<?php

namespace Lathe\Permission\Tests\Fixtures;

use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;

/**
 * A host Resource guarded by a Spatie permission, to watch a role change reach the next request.
 */
class ReportResource extends Resource
{
    public static ?string $policy = ReportPolicy::class;

    public static function uriKey(): string
    {
        return 'reports';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(TestUser::class)->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::id()];
    }
}
