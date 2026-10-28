<?php

it('shows Orders in light and dark at the top of the README at 1440 by 900, each image at most 300 KB', function () {
    $root = dirname(__DIR__, 3);

    expect((string) file_get_contents("{$root}/README.md"))
        ->toContain('<source media="(prefers-color-scheme: dark)" srcset="docs/images/orders-dark.png">')
        ->toContain('<img src="docs/images/orders-light.png"');

    foreach (['orders-light.png', 'orders-dark.png'] as $image) {
        $path = "{$root}/docs/images/{$image}";
        // A PNG's width and height sit in its IHDR chunk, 16 bytes in.
        ['width' => $width, 'height' => $height] = unpack('Nwidth/Nheight', (string) file_get_contents($path, length: 8, offset: 16));

        expect(filesize($path))->toBeLessThanOrEqual(300_000, "{$image} is over 300 KB")
            ->and($width)->toBe(1440)
            ->and($height)->toBe(900);
    }
});
