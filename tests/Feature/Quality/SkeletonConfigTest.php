<?php

// A published config/lathe.php in the shared Testbench skeleton silently replaces the
// package config for every later test and for `testbench serve`. A test that redirects
// app paths also repoints ServiceProvider::$publishes (see NoTerminalTest's isolatedApp()).
it('keeps a published lathe config out of the shared Testbench skeleton', function () {
    $published = dirname(__DIR__, 3).'/vendor/orchestra/testbench-core/laravel/config/lathe.php';

    expect(file_exists($published))->toBeFalse("A test published {$published}; delete it and find the test that wrote it.");
});
