<?php

namespace Lathe\Permission\Tests\Fixtures;

use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Spatie\Permission\PermissionRegistrar;

/**
 * A second host Resource on Spatie's role model that steps aside as its canonical Resource.
 */
class NonCanonicalRoleResource extends Resource
{
    public static function uriKey(): string
    {
        return 'team-roles';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(app(PermissionRegistrar::class)->getRoleClass())->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::id()];
    }
}
