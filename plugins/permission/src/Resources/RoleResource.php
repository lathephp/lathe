<?php

namespace Lathe\Permission\Resources;

use Illuminate\Database\Eloquent\Model;
use Lathe\Enums\Icon;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Permission\Fields\PermissionMultiSelectField;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Spatie\Permission\PermissionRegistrar;

/**
 * Spatie's roles, on the role class the registrar names.
 */
class RoleResource extends Resource
{
    use AccessControlFields;

    public static function uriKey(): string
    {
        return 'roles';
    }

    public static function label(): string
    {
        return 'Roles';
    }

    public static function singularLabel(): string
    {
        return 'Role';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        /** @var class-string<Model> $model */
        $model = app(PermissionRegistrar::class)->getRoleClass();

        return $definition->model($model)
            ->title('name')
            ->searchable(['id', 'name'])
            ->group('Access Control')
            ->icon(Icon::ShieldCheck->value);
    }

    public function fields(LatheRequest $request): array
    {
        return $this->accessControlFields(config()->string('permission.table_names.roles', 'roles'), new PermissionMultiSelectField);
    }
}
