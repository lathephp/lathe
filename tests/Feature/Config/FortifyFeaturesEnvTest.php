<?php

it('reads the panel Fortify features from LATHE_FORTIFY_FEATURES', function () {
    putenv('LATHE_FORTIFY_FEATURES=update-passwords, two-factor-authentication');
    $_ENV['LATHE_FORTIFY_FEATURES'] = 'update-passwords, two-factor-authentication';

    try {
        $config = require dirname(__DIR__, 3).'/config/lathe.php';
    } finally {
        putenv('LATHE_FORTIFY_FEATURES');
        unset($_ENV['LATHE_FORTIFY_FEATURES']);
    }

    expect($config['fortify']['features'])->toBe(['update-passwords', 'two-factor-authentication']);
});

it('turns no feature on without it', function () {
    $config = require dirname(__DIR__, 3).'/config/lathe.php';

    expect($config['fortify']['features'])->toBe([]);
});
