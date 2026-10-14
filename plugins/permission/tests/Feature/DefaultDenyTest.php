<?php

use Illuminate\Support\Facades\Log;
use Lathe\Lathe;
use Lathe\Permission\PermissionPlugin;
use Lathe\Permission\Policies\PermissionPolicy;
use Lathe\Permission\Policies\RolePolicy;
use Lathe\Permission\Tests\Fixtures\AppPermissionPolicy;
use Lathe\Permission\Tests\Fixtures\AppRolePolicy;

it('denies an admin who passes viewLathe the roles endpoint without the opt-in', function () {
    $this->signInAdmin();

    $this->getJson('/lathe/api/resources/roles')->assertForbidden();
    $this->getJson('/lathe/api/resources/permissions')->assertForbidden();
});

it('serves the roles endpoint to that admin after withDefaultPolicies()', function () {
    $this->registerOnly(PermissionPlugin::make()->withDefaultPolicies());
    $this->signInAdmin();

    $this->getJson('/lathe/api/resources/roles')->assertOk();
    $this->getJson('/lathe/api/resources/permissions')->assertOk();
});

it('warns once per shipped policy class however many requests follow', function () {
    Log::spy();

    $this->registerOnly(PermissionPlugin::make()->withDefaultPolicies());
    $this->signInAdmin();

    foreach (range(1, 100) as $request) {
        $this->getJson('/lathe/api/resources/roles')->assertOk();
    }

    Log::shouldHaveReceived('warning')->twice();
    Log::shouldHaveReceived('warning')->with(RolePolicy::class.' allows every ability to any user who passes viewLathe.')->once();
    Log::shouldHaveReceived('warning')->with(PermissionPolicy::class.' allows every ability to any user who passes viewLathe.')->once();
});

it('logs no warning for the app policies useRolePolicy() and usePermissionPolicy() register', function () {
    Log::spy();

    $this->registerOnly(PermissionPlugin::make()->useRolePolicy(AppRolePolicy::class)->usePermissionPolicy(AppPermissionPolicy::class));
    $this->signInAdmin();

    $this->getJson('/lathe/api/resources/roles')->assertOk();

    Log::shouldNotHaveReceived('warning');
});

it('answers 403 through the core plugin controller for a user the plugin denies and 404 for an unknown plugin', function () {
    Lathe::plugins()->find('permission')?->canSee(fn () => false);
    $this->signInAdmin();

    $this->getJson('/lathe/api/plugins/permission/x')->assertForbidden();
    $this->getJson('/lathe/api/plugins/nobody/x')->assertNotFound();
});
