<?php

namespace Lathe\Permission\Resources;

use Illuminate\Database\Eloquent\Model;
use Lathe\Field\Field;
use Lathe\Field\Relations\MorphToManyField;
use Lathe\Lathe;

/**
 * The fields roles and permissions share, around the picker of the other side.
 */
trait AccessControlFields
{
    /**
     * @return list<Field>
     */
    protected function accessControlFields(string $table, Field $picker): array
    {
        return array_values(array_filter([
            Field::id()->sortable(),
            Field::text('Name', 'name')
                ->sortable()
                ->required()
                ->rules('string', 'max:255')
                ->creationRules("unique:{$table},name")
                ->updateRules("unique:{$table},name,{{resourceId}}"),
            $this->guardField(),
            $picker,
            $this->usersField(),
            Field::dateTime('Created At', 'created_at')->sortable()->exceptOnForms(),
        ]));
    }

    /**
     * A choice of guard, only when the app configures more than one.
     */
    protected function guardField(): ?Field
    {
        /** @var array<string, mixed> $config */
        $config = config('auth.guards', []);
        $guards = array_keys($config);

        if (count($guards) < 2) {
            return null;
        }

        return Field::select('Guard Name', 'guard_name')
            ->options(array_combine($guards, $guards))
            ->default(config('permission.default_guard') ?? config('auth.defaults.guard'))
            ->required();
    }

    /**
     * The users of the record, through the Resource the host registered for its user model.
     */
    protected function usersField(): ?MorphToManyField
    {
        /** @var class-string<Model>|null $model */
        $model = config('auth.providers.users.model');
        $resource = $model === null ? null : Lathe::resources()->forModel($model);

        return $resource === null ? null : Field::morphToMany($resource::label(), 'users', $resource);
    }
}
