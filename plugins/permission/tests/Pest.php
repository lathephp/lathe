<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lathe\Permission\Tests\Discovery\TestCase as DiscoveryTestCase;
use Lathe\Permission\Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(DiscoveryTestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Discovery');
