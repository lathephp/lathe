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
 * The permissions of a role, or the direct permissions of a user. The options are searched
 * on the server, so a form reads no permission until the user types. Spatie's
 * syncPermissions() runs after the save, inside its transaction.
 */
class PermissionMultiSelectField extends MultiSelectField
{
    use SyncsNames;

    public function __construct(string $label = 'Permissions', string|Closure|null $attribute = 'permissions', ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);

        /** @var class-string<Model> $permission */
        $permission = app(PermissionRegistrar::class)->getPermissionClass();

        $this->remoteOptions(fn (): Builder => $permission::query(), 'name');

        $this->resolveUsing(function (object $record): array {
            $names = method_exists($record, 'getPermissionNames') ? $record->getPermissionNames() : null;

            return $names instanceof Collection ? $names->all() : [];
        });

        $this->fillUsing(function (LatheRequest $request, object $record, string $attribute): ?Closure {
            if (! $request->exists($attribute) || ! method_exists($record, 'syncPermissions')) {
                return null;
            }

            $names = self::names($request->input($attribute));

            return fn () => $record->syncPermissions($names);
        });

        $this->hideFromIndex();
    }
}
