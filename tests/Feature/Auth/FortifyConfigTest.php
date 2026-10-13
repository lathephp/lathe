<?php

use Lathe\Auth\FortifyConfig;
use Lathe\Lathe;
use Workbench\App\Models\User;

it('uses the default guard and broker when lathe sets none', function () {
    config(['auth.defaults.guard' => 'web', 'auth.defaults.passwords' => 'users']);

    $fortify = app(FortifyConfig::class);

    expect($fortify->guard())->toBe('web')
        ->and($fortify->passwordBroker())->toBe('users');
});

it('reads the guard and broker from lathe.guard and lathe.passwords', function () {
    config(['lathe.guard' => 'admin', 'lathe.passwords' => 'admins']);

    $fortify = app(FortifyConfig::class);

    expect($fortify->guard())->toBe('admin')
        ->and($fortify->passwordBroker())->toBe('admins');
});

it('takes the user model from the provider of the panel guard', function () {
    config([
        'auth.guards.admin' => ['driver' => 'session', 'provider' => 'admins'],
        'auth.providers.admins' => ['driver' => 'eloquent', 'model' => 'App\\Models\\Admin'],
        'auth.providers.users.model' => User::class,
    ]);

    $fortify = app(FortifyConfig::class);
    expect($fortify->userModel())->toBe(User::class);

    config(['lathe.guard' => 'admin']);
    expect($fortify->userModel())->toBe('App\\Models\\Admin');
});

it('uses email as the username and email field by default', function () {
    $fortify = Lathe::fortify();

    expect($fortify->username())->toBe('email')
        ->and($fortify->email())->toBe('email');

    config(['lathe.fortify.username' => 'login', 'lathe.fortify.email' => 'contact']);

    expect($fortify->username())->toBe('login')
        ->and($fortify->email())->toBe('contact');
});

it('turns on no feature on a fresh install', function () {
    $fortify = app(FortifyConfig::class);

    expect($fortify->features())->toBe([])
        ->and($fortify->featureEnabled('twoFactorAuthentication'))->toBeFalse();
});

it('reads its features from lathe.fortify.features', function () {
    config(['lathe.fortify.features' => ['two-factor-authentication', 'update-passwords']]);

    $fortify = app(FortifyConfig::class);

    expect($fortify->features())->toBe(['two-factor-authentication', 'update-passwords'])
        ->and($fortify->featureEnabled('twoFactorAuthentication'))->toBeTrue()
        ->and($fortify->featureEnabled('two-factor-authentication'))->toBeTrue()
        ->and($fortify->featureEnabled('updatePasswords'))->toBeTrue()
        ->and($fortify->featureEnabled('emailVerification'))->toBeFalse();
});
