<?php

namespace Lathe\Permission\Fields;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lathe\Field\MultiSelectField;
use Lathe\Http\Requests\LatheRequest;
use Spatie\Permission\PermissionRegistrar;

/**
 * The roles of a user, or the roles that hold a permission. Up to 100 roles are sent with the
 * form; past that the client searches them on the server. Spatie's syncRoles() runs after the
 * save, inside its transaction.
 */
class RoleMultiSelectField extends MultiSelectField
{
    use SyncsNames;

    public function __construct(string $label = 'Roles', string|Closure|null $attribute = 'roles', ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);

        /** @var class-string<Model> $role */
        $role = app(PermissionRegistrar::class)->getRoleClass();

        $this->remoteOptions(fn (): Builder => $role::query(), 'name', 100);

        $this->resolveUsing(function (object $record): array {
            $names = method_exists($record, 'getRoleNames') ? $record->getRoleNames() : null;

            return $names instanceof Collection ? $names->all() : [];
        });

        $this->fillUsing(function (LatheRequest $request, object $record, string $attribute): ?Closure {
            if (! $request->exists($attribute) || ! method_exists($record, 'syncRoles')) {
                return null;
            }

            $names = self::names($request->input($attribute));

            return fn () => $record->syncRoles($names);
        });

        $this->hideFromIndex();
    }
}
