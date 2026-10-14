<?php

namespace Lathe\Permission\Fields;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lathe\Field\SelectField;
use Lathe\Http\Requests\LatheRequest;
use Spatie\Permission\PermissionRegistrar;

/**
 * The one role of a user who holds at most one. Up to 100 roles are sent with the form; past
 * that the client searches them on the server. Spatie's syncRoles() runs after the save,
 * inside its transaction.
 */
class RoleSelectField extends SelectField
{
    public function __construct(string $label = 'Role', string|Closure|null $attribute = 'role', ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);

        /** @var class-string<Model> $role */
        $role = app(PermissionRegistrar::class)->getRoleClass();

        $this->remoteOptions(fn (): Builder => $role::query(), 'name', 100);

        $this->resolveUsing(function (object $record): mixed {
            $names = method_exists($record, 'getRoleNames') ? $record->getRoleNames() : null;

            return $names instanceof Collection ? $names->first() : null;
        });

        $this->fillUsing(function (LatheRequest $request, object $record, string $attribute): ?Closure {
            if (! $request->exists($attribute) || ! method_exists($record, 'syncRoles')) {
                return null;
            }

            $name = $request->input($attribute);
            $names = is_string($name) && $name !== '' ? [$name] : [];

            return fn () => $record->syncRoles($names);
        });

        $this->hideFromIndex();
    }
}
