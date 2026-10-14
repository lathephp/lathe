<?php

/**
 * Every leaf key of the package config, as dotted paths. A list or a map of colour
 * tokens is one key: the page documents `theme.colors`, not each of the 40 tokens.
 *
 * @param  array<array-key, mixed>  $config
 * @return list<string>
 */
function configDocsKeys(array $config, string $prefix = ''): array
{
    $keys = [];

    foreach ($config as $key => $value) {
        $path = $prefix.$key;

        if (is_array($value) && $value !== [] && ! array_is_list($value) && $path !== 'theme.colors') {
            $keys = [...$keys, ...configDocsKeys($value, $path.'.')];
        } else {
            $keys[] = $path;
        }
    }

    return $keys;
}

it('documents every config key on the configuration page', function () {
    $root = dirname(__DIR__, 3);
    $page = (string) file_get_contents("{$root}/docs/getting-started/configuration.md");
    $config = require "{$root}/config/lathe.php";

    $missing = array_values(array_filter(configDocsKeys($config), fn (string $key) => ! str_contains($page, "`{$key}`")));

    expect($missing)->toBe([], 'docs/getting-started/configuration.md does not name: '.implode(', ', $missing));
});
