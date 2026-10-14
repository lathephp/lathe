<?php

namespace Lathe\Testing;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Testing\TestResponse;
use Lathe\Auth\FortifyConfig;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Symfony\Component\HttpFoundation\Response;

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

    /**
     * The dashboards the user may see.
     *
     * @return TestResponse<Response>
     */
    public static function dashboards(): TestResponse
    {
        return (new DashboardTester(''))->getJson(rtrim(Lathe::path(), '/').'/api/dashboards');
    }

    /**
     * A dashboard by its URI key.
     */
    public static function dashboard(string $uriKey): DashboardTester
    {
        return new DashboardTester($uriKey);
    }
}
