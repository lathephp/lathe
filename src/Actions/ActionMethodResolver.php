<?php

namespace Lathe\Actions;

use Illuminate\Support\Str;

/**
 * Picks the handler an action defines for a model class: handleForOrders, then
 * handleForOrder, then handle. Failure callbacks follow the same order.
 */
class ActionMethodResolver
{
    /**
     * @param  class-string  $modelClass
     */
    public static function handlerFor(Action $action, string $modelClass): string
    {
        return static::firstDefined($action, 'handle', $modelClass) ?? 'handle';
    }

    /**
     * The failure callback for the model class, or null when the action defines none.
     *
     * @param  class-string  $modelClass
     */
    public static function failedHandlerFor(Action $action, string $modelClass): ?string
    {
        return static::firstDefined($action, 'failed', $modelClass)
            ?? (method_exists($action, 'failed') ? 'failed' : null);
    }

    /**
     * @param  class-string  $modelClass
     */
    protected static function firstDefined(Action $action, string $prefix, string $modelClass): ?string
    {
        $basename = class_basename($modelClass);

        foreach ([$prefix.'For'.Str::plural($basename), $prefix.'For'.$basename] as $method) {
            if (method_exists($action, $method)) {
                return $method;
            }
        }

        return null;
    }
}
