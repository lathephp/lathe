<?php

namespace Lathe\Permission\Tests\Fixtures;

use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Permission\Fields\RoleMultiSelectField;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;

/**
 * The host app's User Resource, which the plugin looks up by the auth user model. Roles are
 * assigned here, as a host app does on its User edit page.
 */
class TestUserResource extends Resource
{
    public static ?string $policy = UserPolicy::class;

    public static function uriKey(): string
    {
        return 'users';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(TestUser::class);
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::id(), new RoleMultiSelectField];
    }
}
