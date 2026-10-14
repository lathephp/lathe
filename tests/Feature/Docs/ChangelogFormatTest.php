<?php

it('keeps a changelog that starts at the first beta', function () {
    $changelog = (string) file_get_contents(dirname(__DIR__, 3).'/CHANGELOG.md');

    preg_match_all('/^## \[([^\]]+)\]/m', $changelog, $versions);

    expect($changelog)->toContain('[Keep a Changelog]', '[Semantic Versioning]')
        ->and($versions[1])->toBe(['Unreleased', '1.0.0-beta.1']);
});
