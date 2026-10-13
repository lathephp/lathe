<?php

use Lathe\Lathe;
use Lathe\LatheApplicationServiceProvider;

it('discovers the app Resources under app/Lathe/Resources by default', function () {
    Lathe::resources()->reset();
    $provider = new class(app()) extends LatheApplicationServiceProvider {};

    $provider->boot();

    $directories = (fn () => $this->directories)->call(Lathe::resources());
    expect($directories)->toBe([[app_path('Lathe/Resources'), 'App\\Lathe\\Resources']]);
});
