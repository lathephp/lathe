<?php

use Illuminate\Support\Facades\Log;
use Lathe\Lathe;

it('logs one warning per allow-all policy class however often it is asked', function () {
    Log::spy();

    foreach (range(1, 100) as $request) {
        Lathe::warnAllowAll('App\Policies\RolePolicy');
        Lathe::warnAllowAll('App\Policies\PermissionPolicy');
    }

    Log::shouldHaveReceived('warning')->twice();
    Log::shouldHaveReceived('warning')->with('App\Policies\RolePolicy allows every ability to any user who passes viewLathe.')->once();
});
