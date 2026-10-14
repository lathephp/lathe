<?php

namespace Lathe\Permission\Tests\Fixtures;

use Spatie\Permission\PermissionRegistrar;

/**
 * Spatie's registrar, counting how often its cache is flushed.
 */
class CountingRegistrar extends PermissionRegistrar
{
    public int $flushes = 0;

    public function forgetCachedPermissions(): bool
    {
        $this->flushes++;

        return parent::forgetCachedPermissions();
    }
}
