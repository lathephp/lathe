<?php

use Lathe\Lathe;
use Lathe\Testing\InteractsWithLathe;
use Workbench\App\Lathe\Resources\Order;

uses(InteractsWithLathe::class);

it('clears the registry and the cached manifest', function () {
    Lathe::registerResources(Order::class);
    Lathe::resources()->cache();
    expect(Lathe::resources()->forKey('orders'))->not->toBeNull()
        ->and(file_exists(Lathe::resources()->cachePath()))->toBeTrue();

    $this->flushLathe();

    expect(Lathe::resources()->all())->toBe([])
        ->and(file_exists(Lathe::resources()->cachePath()))->toBeFalse();
});

it('registers Resources without a provider', function () {
    $this->flushLathe();
    $this->registerResources(Order::class);

    expect(Lathe::resources()->forKey('orders'))->toBe(Order::class);
});
