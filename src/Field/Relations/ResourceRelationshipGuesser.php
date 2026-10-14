<?php

namespace Lathe\Field\Relations;

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceRegistry;

/**
 * Guesses a relation field's relation name from its label and its related Resource from the
 * owning Resource's model. It reads no backtrace.
 */
class ResourceRelationshipGuesser
{
    /**
     * "Customer" becomes customer, "User Profile" becomes userProfile.
     */
    public static function guessRelation(string $label): string
    {
        return Str::camel(str_replace(' ', '_', $label));
    }

    /**
     * The canonical Resource of the relation's related model, else the conventional class
     * when it exists.
     *
     * @param  class-string<resource>|null  $owner
     * @return class-string<resource>|null
     */
    public static function guessResource(string $label, string $relation, ?string $owner): ?string
    {
        if ($owner !== null) {
            $model = $owner::newModel();

            if (method_exists($model, $relation)) {
                $instance = $model->{$relation}();

                if ($instance instanceof Relation && ! $instance instanceof MorphTo) {
                    $found = Container::getInstance()->make(ResourceRegistry::class)->forModel($instance->getRelated()::class);

                    if ($found !== null) {
                        return $found;
                    }
                }
            }
        }

        $conventional = static::conventionalResource($label);

        return class_exists($conventional) && is_subclass_of($conventional, Resource::class) ? $conventional : null;
    }

    /**
     * App\Lathe\Resources\{StudlySingular} under the application's namespace.
     */
    public static function conventionalResource(string $label): string
    {
        $app = Container::getInstance();
        $namespace = $app instanceof Application ? rtrim($app->getNamespace(), '\\') : 'App';

        return $namespace.'\\Lathe\\Resources\\'.Str::studly(Str::singular($label));
    }
}
