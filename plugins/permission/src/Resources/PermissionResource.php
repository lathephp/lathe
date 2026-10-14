<?php

namespace Lathe\Permission\Resources;

use Illuminate\Database\Eloquent\Model;
use Lathe\Enums\Icon;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Permission\Fields\RoleMultiSelectField;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Spatie\Permission\PermissionRegistrar;

/**
 * Spatie's permissions, on the permission class the registrar names.
 */
class PermissionResource extends Resource
{
    use AccessControlFields;

    public static function uriKey(): string
    {
        return 'permissions';
    }

    public static function label(): string
    {
        return 'Permissions';
    }

    public static function singularLabel(): string
    {
        return 'Permission';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        /** @var class-string<Model> $model */
        $model = app(PermissionRegistrar::class)->getPermissionClass();

        return $definition->model($model)
            ->title('name')
            ->searchable(['id', 'name'])
            ->group('Access Control')
            ->icon(Icon::Key->value);
    }

    public function fields(LatheRequest $request): array
    {
        return $this->accessControlFields(config()->string('permission.table_names.permissions', 'permissions'), new RoleMultiSelectField);
    }
}
