<?php

namespace Lathe\Testing;

use Illuminate\Contracts\Auth\Authenticatable;
use Lathe\Auth\FortifyConfig;
use Lathe\Resources\Resource;

/**
 * The entry point for requests to a panel under test.
 */
class LatheTest
{
    /**
     * Sign in on the panel guard.
     */
    public static function actingAs(Authenticatable $user): void
    {
        $guard = app(FortifyConfig::class)->guard();

        auth()->guard($guard)->setUser($user);
        auth()->shouldUse($guard);
    }

    /**
     * A Resource class or its URI key.
     */
    public static function resource(string $classOrKey): ResourceTester
    {
        return new ResourceTester(is_subclass_of($classOrKey, Resource::class) ? $classOrKey::uriKey() : $classOrKey);
    }
}
