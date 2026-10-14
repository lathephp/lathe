<?php

namespace Lathe\Permission\Tests\Fixtures;

use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;

/**
 * The host app's User Resource, which the plugin looks up by the auth user model.
 */
class TestUserResource extends Resource
{
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
        return [Field::id()];
    }
}
