<?php

use Lathe\LatheServiceProvider;

it('boots the package provider in the workbench', function () {
    expect(app()->getProviders(LatheServiceProvider::class))->not->toBeEmpty();
});
