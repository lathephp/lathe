<?php

namespace Lathe\Permission\Resources;

use Illuminate\Database\Eloquent\Model;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Spatie\Permission\PermissionRegistrar;

/**
 * Spatie's roles, on the role class the registrar names.
 */
class RoleResource extends Resource
{
    public static function uriKey(): string
    {
        return 'roles';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        /** @var class-string<Model> $model */
        $model = app(PermissionRegistrar::class)->getRoleClass();

        return $definition->model($model);
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::id()];
    }
}
