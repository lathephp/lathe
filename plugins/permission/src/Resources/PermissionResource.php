<?php

namespace Lathe\Permission\Resources;

use Illuminate\Database\Eloquent\Model;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Spatie\Permission\PermissionRegistrar;

/**
 * Spatie's permissions, on the permission class the registrar names.
 */
class PermissionResource extends Resource
{
    public static function uriKey(): string
    {
        return 'permissions';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        /** @var class-string<Model> $model */
        $model = app(PermissionRegistrar::class)->getPermissionClass();

        return $definition->model($model);
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::id()];
    }
}
