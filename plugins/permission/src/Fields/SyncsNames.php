<?php

namespace Lathe\Permission\Fields;

use Lathe\Auth\ResourceAuthorization;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Permission\PermissionPlugin;

/**
 * Reads the names a picker submitted, as an array or its JSON text, without blanks, and hides
 * a field from a user who may not list the Resource it picks from.
 */
trait SyncsNames
{
    /**
     * @return list<string>
     */
    protected static function names(mixed $value): array
    {
        $names = is_string($value) ? json_decode($value, true) : $value;

        return is_array($names) ? array_values(array_filter($names, fn (mixed $name) => is_string($name) && $name !== '')) : [];
    }

    /**
     * Asks viewAny on the Role (0) or Permission (1) Resource the plugin registered, so the
     * form, the save and the picker all stay shut for a user who fails it.
     *
     * @internal
     */
    protected function seenWithViewAnyOn(int $resource): static
    {
        return $this->canSee(function (LatheRequest $request) use ($resource): bool {
            $plugin = Lathe::plugins()->find('permission');
            $class = $plugin instanceof PermissionPlugin ? ($plugin->resources()[$resource] ?? null) : null;

            return $class !== null && app(ResourceAuthorization::class)->allows($request->user(), $class, 'viewAny');
        });
    }
}
