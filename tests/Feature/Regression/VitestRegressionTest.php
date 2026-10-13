<?php

/*
 * Vitest has no Pest groups, so the client regression tests are pinned here by their names:
 * each must stay an it() title under resources/js/__tests__.
 */
const LATHE_VITEST_REGRESSIONS = [
    'date_cell_click_navigates',
    'collapsible_toggle_does_not_submit_form',
    'server_error_shows_retry_toast',
    'multi_select_keeps_selection',
];

it('keeps_each_client_regression_test', function (string $name) {
    $sources = implode("\n", array_map(
        fn (string $file) => (string) file_get_contents($file),
        glob(dirname(__DIR__, 3).'/resources/js/__tests__/*.test.{ts,tsx}', GLOB_BRACE) ?: [],
    ));

    expect($sources)->toContain("it('{$name}'");
})->with(LATHE_VITEST_REGRESSIONS)->group('regression');
