<?php

it('keeps a changelog that starts at the first beta', function () {
    $changelog = (string) file_get_contents(dirname(__DIR__, 3).'/CHANGELOG.md');

    preg_match_all('/^## \[([^\]]+)\]/m', $changelog, $versions);

    expect($changelog)->toContain('[Keep a Changelog]', '[Semantic Versioning]')
        ->and($versions[1][0])->toBe('Unreleased')
        ->and(end($versions[1]))->toBe('1.0.0-beta.1');
});
