<?php

/*
 * Every browser flow, taken from the Show lines of the spec, as id => test file in this folder.
 * FlowCoverageTest fails for a listed flow whose file has no test.
 */

return [
    'login' => 'LoginTest.php',
    'orders-index' => 'OrdersIndexTest.php',
    'heartbeat' => 'HeartbeatTest.php',
    'sidebar' => 'SidebarTest.php',
    'forbidden' => 'ForbiddenTest.php',
    'not-found' => 'NotFoundTest.php',
    'stale-assets' => 'StaleAssetsTest.php',
    'plugin-load-failure' => 'PluginLoadFailureTest.php',
    'index-search-sort' => 'IndexSearchSortTest.php',
    'search-palette' => 'SearchPaletteTest.php',
    'index-filters' => 'IndexFiltersTest.php',
    'lens' => 'LensTest.php',
    'index-rows' => 'IndexRowsTest.php',
];
