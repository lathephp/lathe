<?php

use Illuminate\Support\Facades\File;

/**
 * The README and every package description, keyed by path.
 *
 * @return array<string, string>
 */
function brandedTexts(): array
{
    $root = dirname(__DIR__, 3);
    $texts = ['README.md' => File::get("{$root}/README.md")];

    foreach ([...glob("{$root}/composer.json") ?: [], ...glob("{$root}/plugins/*/composer.json") ?: [], ...glob("{$root}/package.json") ?: [], ...glob("{$root}/packages/*/package.json") ?: []] as $manifest) {
        $description = json_decode(File::get($manifest), true)['description'] ?? '';
        $texts[substr($manifest, strlen($root) + 1)] = is_string($description) ? $description : '';
    }

    return $texts;
}

it('never names the product as part of Laravel', function () {
    $offending = array_keys(array_filter(brandedTexts(), fn (string $text) => preg_match('/Laravel\s+Lathe/i', $text) === 1));

    expect($offending)->toBe([]);
});

it('describes the package as an admin panel builder for Laravel', function () {
    expect(json_decode(File::get(dirname(__DIR__, 3).'/composer.json'), true)['description'])
        ->toBe('Admin panel builder for Laravel on Inertia, React and shadcn/ui.');
});

it('gives the README the install flow with no private repository or key step', function () {
    $readme = File::get(dirname(__DIR__, 3).'/README.md');

    expect($readme)->toContain('PHP 8.4', 'Laravel 13', 'composer require lathe/lathe', 'php artisan lathe:install', 'php artisan lathe:user', 'gate()', '/lathe', 'SECURITY.md', 'composer serve', 'composer qa', 'lathephp.com/docs', 'demo.lathephp.com')
        ->and(strpos($readme, 'lathe:install'))->toBeLessThan(strpos($readme, 'lathe:user'))
        ->and($readme)->not->toMatch('/http-basic|"repositories"|license[_ ]key|LICENSE_KEY/i');
});
