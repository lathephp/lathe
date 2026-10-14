<?php

namespace Lathe\Permission\Tests\Fixtures;

use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Spatie\Permission\PermissionRegistrar;

/**
 * A host app's own Role Resource, already holding the `roles` uriKey.
 */
class HostRoleResource extends Resource
{
    public static function uriKey(): string
    {
        return 'roles';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(app(PermissionRegistrar::class)->getRoleClass());
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::id()];
    }
}
